<?php

namespace App\Services\Imports;

use InvalidArgumentException;

class PermitImportHeaderMapper
{
    private const REQUIRED = [
        'plate_number' => ['plat motor'],
        'employee_name' => ['nama'],
        'nik' => ['nik'],
        'department' => ['dep'],
        'section' => ['bagian'],
        'position' => ['jabatan'],
        'parking_location' => ['lokasi parkir'],
        'route_raw' => ['rute kendaraan'],
        'reason' => ['alasan masuk'],
        'permit_color' => ['warna kartu'],
        'contact_number' => ['nomor kontak'],
        'approval_status' => ['hasil persetujuan'],
        'division' => ['divisi'],
    ];

    public function findHeader(array $rows)
    {
        foreach ($rows as $rowIndex => $row) {
            $columns = $this->mapColumns($row);

            if ($this->hasRequiredColumns($columns)) {
                return [
                    'row_index' => $rowIndex,
                    'columns' => $columns,
                ];
            }
        }

        throw new InvalidArgumentException('Header Excel tidak valid: kolom wajib tidak ditemukan.');
    }

    private function mapColumns(array $row)
    {
        $columns = [];

        foreach ($row as $index => $label) {
            $normalized = $this->normalizeLabel($label);

            foreach (self::REQUIRED as $key => $needles) {
                foreach ($needles as $needle) {
                    if ($normalized !== '' && strpos($normalized, $needle) !== false) {
                        $columns[$key] = $index;
                    }
                }
            }

            if ($normalized === 'ket') {
                $columns['notes'] = $index;
            }
        }

        return $columns;
    }

    private function hasRequiredColumns(array $columns)
    {
        foreach (array_keys(self::REQUIRED) as $required) {
            if (!array_key_exists($required, $columns)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeLabel($value)
    {
        $value = strtolower((string) $value);
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}
