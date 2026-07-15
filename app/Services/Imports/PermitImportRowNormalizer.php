<?php

namespace App\Services\Imports;

use App\Models\ImportRow;

class PermitImportRowNormalizer
{
    private $routeParser;

    public function __construct(RouteSegmentParser $routeParser)
    {
        $this->routeParser = $routeParser;
    }

    public function normalize(array $rawRow, array $headerColumns, array $activeRouteCodes, int $rowNumber)
    {
        $rawData = [
            'row_number' => $rowNumber,
            'plate_number' => $this->cell($rawRow, $headerColumns, 'plate_number'),
            'employee_name' => $this->cell($rawRow, $headerColumns, 'employee_name'),
            'nik' => $this->cell($rawRow, $headerColumns, 'nik'),
            'department' => $this->cell($rawRow, $headerColumns, 'department'),
            'section' => $this->cell($rawRow, $headerColumns, 'section'),
            'position' => $this->cell($rawRow, $headerColumns, 'position'),
            'parking_location' => $this->cell($rawRow, $headerColumns, 'parking_location'),
            'route_raw' => $this->cell($rawRow, $headerColumns, 'route_raw'),
            'reason' => $this->cell($rawRow, $headerColumns, 'reason'),
            'permit_color' => $this->cell($rawRow, $headerColumns, 'permit_color'),
            'contact_number' => $this->cell($rawRow, $headerColumns, 'contact_number'),
            'approval_status' => $this->cell($rawRow, $headerColumns, 'approval_status'),
            'notes' => $this->cell($rawRow, $headerColumns, 'notes'),
            'division' => $this->cell($rawRow, $headerColumns, 'division'),
        ];

        $errors = [];
        $warnings = [];

        $plate = $this->normalizeText($rawData['plate_number']);
        $name = $this->normalizeText($rawData['employee_name']);
        $nik = $this->normalizeText($rawData['nik']);
        $parkingCodes = $this->normalizeParkingCodes($rawData['parking_location']);
        $parking = $parkingCodes[0] ?? '';
        $routeRaw = $this->normalizeText($rawData['route_raw']);
        $color = $this->normalizeColor($rawData['permit_color']);
        $approved = $this->isApproved($rawData['approval_status']);

        if ($nik === '') {
            $errors[] = 'NIK wajib diisi';
        }

        if ($name === '') {
            $errors[] = 'Nama wajib diisi';
        }

        if ($plate === '') {
            $errors[] = 'Plat motor wajib diisi';
        }

        if (!$approved) {
            $errors[] = 'Hasil persetujuan harus disetujui';
        }

        if ($color === null) {
            $errors[] = 'Warna kartu izin tidak valid';
        }

        if ($plate !== '' && $this->containsMultiplePlates($rawData['plate_number'])) {
            $warnings[] = 'Plat motor berisi lebih dari satu nilai';
        }

        if ($parkingCodes === []) {
            $warnings[] = 'Lokasi parkir kosong';
        }

        $route = $this->routeParser->parse($routeRaw, $activeRouteCodes, $parkingCodes);
        $warnings = array_merge($warnings, $route['warnings']);

        $status = ImportRow::STATUS_VALID;
        if ($errors !== []) {
            $status = ImportRow::STATUS_INVALID;
        } elseif ($warnings !== []) {
            $status = ImportRow::STATUS_NEEDS_REVIEW;
        }

        return [
            'row_number' => $rowNumber,
            'status' => $status,
            'raw_data' => $rawData,
            'normalized_data' => [
                'nik' => $nik,
                'employee_name' => $name,
                'department' => $this->normalizeText($rawData['department']),
                'section' => $this->normalizeText($rawData['section']),
                'position' => $this->normalizeText($rawData['position']),
                'division' => $this->normalizeText($rawData['division']),
                'contact_number' => $this->normalizeText($rawData['contact_number']),
                'plate_number' => $plate,
                'parking_location_code' => $parking,
                'parking_location_codes' => $parkingCodes,
                'route_raw' => $routeRaw,
                'route_segment_codes' => $route['codes'],
                'reason' => $this->normalizeText($rawData['reason']),
                'permit_color' => $color,
                'approval_status' => $approved ? 'approved' : 'rejected',
                'notes' => $this->normalizeText($rawData['notes']),
            ],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function cell(array $row, array $columns, $key)
    {
        if (!array_key_exists($key, $columns)) {
            return '';
        }

        $index = $columns[$key];

        return array_key_exists($index, $row) ? $row[$index] : '';
    }

    private function normalizeText($value)
    {
        $value = trim((string) $value);
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function normalizeParkingCodes($value)
    {
        $codes = preg_split('/[\r\n,]+|\s+\/\s+/', (string) $value);
        $codes = array_map([$this, 'normalizeText'], $codes ?: []);
        $codes = array_filter($codes, function ($code) {
            return $code !== '';
        });

        return array_values(array_unique($codes));
    }

    private function normalizeColor($value)
    {
        $value = strtolower($this->normalizeText($value));

        if (strpos($value, 'biru') !== false) {
            return 'biru';
        }

        if (strpos($value, 'kuning') !== false) {
            return 'kuning';
        }

        if (strpos($value, 'merah') !== false) {
            return 'merah';
        }

        if (strpos($value, 'hijau') !== false) {
            return 'hijau';
        }

        return null;
    }

    private function isApproved($value)
    {
        $value = $this->normalizeText($value);

        if (in_array($value, ['√', 'âˆš', 'Ã¢Ë†Å¡'], true)) {
            return true;
        }

        return in_array(strtolower($value), ['approved', 'disetujui', 'setuju'], true);
    }

    private function containsMultiplePlates($value)
    {
        $value = (string) $value;

        return strpos($value, '/') !== false
            || strpos($value, ',') !== false
            || strpos($value, "\n") !== false;
    }
}
