<?php

namespace App\Exports;

use App\Models\VehiclePermit;
use App\Services\Reports\PermitReportQuery;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PermitReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private PermitReportQuery $reports;
    private array $filters;

    public function __construct(PermitReportQuery $reports, array $filters)
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
            'NIK',
            'Nama',
            'Departemen',
            'Plat',
            'Tipe Kendaraan',
            'Lokasi Parkir',
            'Warna',
            'Status Izin',
            'Status QR',
            'QR Berlaku Sampai',
            'Valid Dari',
            'Valid Sampai',
            'Status Review',
            'Reviewer',
            'Waktu Review',
            'Catatan Review',
            'Sumber Data',
            'Rute Mentah',
            'Jumlah Segmen Rute',
        ];
    }

    public function map($permit): array
    {
        /** @var VehiclePermit $permit */
        $activeToken = $permit->activeToken;

        return [
            optional($permit->employee)->nik ?? '-',
            optional($permit->employee)->name ?? '-',
            optional($permit->employee)->department ?? '-',
            optional($permit->vehicle)->plate_number ?? '-',
            optional($permit->vehicle)->vehicle_type ?? '-',
            optional($permit->parkingLocation)->code ?? '-',
            $permit->permit_color ?? '-',
            $permit->status ?? '-',
            $this->reports->qrStatusLabel($permit),
            $activeToken && $activeToken->expires_at ? $activeToken->expires_at->format('Y-m-d H:i:s') : '-',
            $permit->valid_from ? $permit->valid_from->format('Y-m-d') : '-',
            $permit->valid_until ? $permit->valid_until->format('Y-m-d') : '-',
            $permit->reviewed_at ? 'Sudah direview' : 'Belum direview',
            optional($permit->reviewer)->name ?? '-',
            $permit->reviewed_at ? $permit->reviewed_at->format('Y-m-d H:i:s') : '-',
            $permit->review_note ?? '-',
            $permit->source ?? '-',
            $permit->route_raw ?? '-',
            (int) ($permit->route_segments_count ?? 0),
        ];
    }
}
