<?php

namespace App\Exports;

use App\Models\RoadSegment;
use App\Models\VehiclePermit;
use App\Services\Imports\RouteSegmentParser;
use App\Services\Reports\PermitReportQuery;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PermitNeedsReviewExport implements FromQuery, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
{
    private const ROUTE_RAW_COLUMN_INDEX = 8;
    private const ROUTE_VALIDATION_STATUS_COLUMN_INDEX = 10;
    private const ROUTE_NEEDS_REPAIR_STATUS = 'Perlu perbaikan rute';

    private PermitReportQuery $reports;
    private RouteSegmentParser $routeParser;
    private array $filters;
    private array $activeRouteCodes;

    public function __construct(PermitReportQuery $reports, array $filters)
    {
        $this->reports = $reports;
        $this->filters = $filters;
        $this->routeParser = new RouteSegmentParser();
        $this->activeRouteCodes = RoadSegment::query()
            ->where('status', RoadSegment::STATUS_ACTIVE)
            ->pluck('code')
            ->map(function ($code) {
                return strtoupper($code);
            })
            ->all();
    }

    public function query()
    {
        return $this->reports->query($this->filters);
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama',
            'Departemen',
            'Plat',
            'Tipe Kendaraan',
            'Lokasi Parkir',
            'Status Izin',
            'Rute Mentah',
            'Rute Tidak Tersedia',
            'Status Validasi Rute',
            'Catatan Review',
        ];
    }

    public function map($permit): array
    {
        /** @var VehiclePermit $permit */
        $routeRaw = $permit->route_raw ?? '';
        $parsedRoute = $this->routeParser->parse($routeRaw, $this->activeRouteCodes);
        $unavailableRoutes = $this->unavailableRouteTokens($routeRaw);
        $routeNeedsRepair = $unavailableRoutes !== [] || $parsedRoute['warnings'] !== [];

        return [
            optional($permit->employee)->nik ?? '-',
            optional($permit->employee)->name ?? '-',
            optional($permit->employee)->department ?? '-',
            optional($permit->vehicle)->plate_number ?? '-',
            optional($permit->vehicle)->vehicle_type ?? '-',
            optional($permit->parkingLocation)->code ?? '-',
            $permit->status ?? '-',
            $routeRaw !== '' ? $routeRaw : '-',
            $unavailableRoutes === [] ? '-' : implode(', ', $unavailableRoutes),
            $routeNeedsRepair ? 'Perlu perbaikan rute' : 'Rute tersedia',
            $permit->review_note ?? '-',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                    $validationStatus = $sheet->getCellByColumnAndRow(self::ROUTE_VALIDATION_STATUS_COLUMN_INDEX, $row)->getValue();

                    if ($validationStatus !== self::ROUTE_NEEDS_REPAIR_STATUS) {
                        continue;
                    }

                    $sheet->getStyleByColumnAndRow(self::ROUTE_RAW_COLUMN_INDEX, $row)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB('FFFDE68A');

                    $sheet->getStyleByColumnAndRow(self::ROUTE_VALIDATION_STATUS_COLUMN_INDEX, $row)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB('FFFBCFE8');
                }
            },
        ];
    }

    private function unavailableRouteTokens($routeRaw): array
    {
        $routeWithoutParkingCodes = preg_replace('/[A-Z]{2,}-[A-Z0-9]+-P\d+/i', ' ', (string) $routeRaw);
        preg_match_all('/[A-Z]{1,3}\d{1,2}/i', $routeWithoutParkingCodes, $matches);

        $tokens = array_values(array_unique(array_map('strtoupper', $matches[0])));

        return array_values(array_filter($tokens, function ($token) {
            return ! in_array($token, $this->activeRouteCodes, true);
        }));
    }
}
