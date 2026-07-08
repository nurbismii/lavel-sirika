<?php

namespace App\Services\Reports;

use App\Models\ScanLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ScanReportQuery
{
    public function filters(array $input): array
    {
        return [
            'date_from' => $this->nullableString($input['date_from'] ?? null) ?: now()->subDays(6)->toDateString(),
            'date_to' => $this->nullableString($input['date_to'] ?? null) ?: now()->toDateString(),
            'result' => $this->nullableString($input['result'] ?? null),
            'scanner_id' => $input['scanner_id'] ?? null,
            'search' => $this->nullableString($input['search'] ?? null),
        ];
    }

    public function query(array $filters)
    {
        $filters = $this->filters($filters);
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to = Carbon::parse($filters['date_to'])->endOfDay();

        $query = ScanLog::query()
            ->with([
                'permit.employee',
                'permit.vehicle',
                'permit.parkingLocation',
                'scanner',
            ])
            ->whereBetween('scanned_at', [$from, $to])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id');

        if ($filters['result']) {
            $query->where('result', $filters['result']);
        }

        if ($filters['scanner_id']) {
            $query->where('scanned_by', $filters['scanner_id']);
        }

        if ($filters['search']) {
            $this->applySearchFilter($query, $filters['search']);
        }

        return $query;
    }

    public function assertExportRange(array $filters): void
    {
        $filters = $this->filters($filters);
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to = Carbon::parse($filters['date_to'])->startOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            ]);
        }

        if ($from->diffInDays($to) > 30) {
            throw ValidationException::withMessages([
                'date_range' => 'Rentang laporan scan maksimal 31 hari.',
            ]);
        }
    }

    public function resultOptions(): array
    {
        return [
            ScanLog::RESULT_VALID => 'Valid',
            ScanLog::RESULT_EXPIRED => 'Kadaluwarsa',
            ScanLog::RESULT_REVOKED => 'Dicabut',
            ScanLog::RESULT_INACTIVE => 'Tidak Aktif',
            ScanLog::RESULT_INVALID => 'Tidak Valid',
        ];
    }

    public function resultLabel(string $result): string
    {
        return $this->resultOptions()[$result] ?? $result;
    }

    public function scannerOptions()
    {
        return User::query()
            ->whereIn('id', ScanLog::query()
                ->whereNotNull('scanned_by')
                ->select('scanned_by'))
            ->orderBy('name')
            ->get();
    }

    private function applySearchFilter($query, string $search): void
    {
        $query->whereHas('permit', function ($permitQuery) use ($search) {
            $permitQuery->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('nik', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            })->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                $vehicleQuery->where('plate_number', 'like', '%' . $search . '%');
            });
        });
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
