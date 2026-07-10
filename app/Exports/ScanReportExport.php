<?php

namespace App\Exports;

use App\Models\ScanLog;
use App\Services\Reports\ScanReportQuery;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ScanReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private ScanReportQuery $reports;
    private array $filters;

    public function __construct(ScanReportQuery $reports, array $filters)
    {
        $this->reports = $reports;
        $this->filters = $filters;
    }

    public function query()
    {
        return $this->reports->query($this->filters);
    }

    public function headings(): array
    {
        return [
            'Waktu Scan',
            'Hasil Scan',
            'Scanner',
            'NIK',
            'Nama',
            'Plat',
            'Lokasi Parkir',
            'Warna',
            'Status Izin',
            'Sumber Izin',
            'Catatan Scan',
            'Device Info',
        ];
    }

    public function map($scanLog): array
    {
        /** @var ScanLog $scanLog */
        $permit = $scanLog->permit;

        return [
            $scanLog->scanned_at ? $scanLog->scanned_at->format('Y-m-d H:i:s') : '-',
            $this->reports->resultLabel($scanLog->result),
            optional($scanLog->scanner)->name ?? '-',
            optional(optional($permit)->employee)->nik ?? '-',
            optional(optional($permit)->employee)->name ?? '-',
            optional(optional($permit)->vehicle)->plate_number ?? '-',
            optional(optional($permit)->parkingLocation)->code ?? '-',
            optional($permit)->permit_color ?? '-',
            optional($permit)->status ?? '-',
            optional($permit)->source ?? '-',
            $scanLog->notes ?? '-',
            $scanLog->device_info ?? '-',
        ];
    }
}
