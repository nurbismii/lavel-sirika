<?php

namespace App\Services\Reports;

use App\Models\PermitToken;
use App\Models\VehiclePermit;
use Illuminate\Support\Facades\DB;

class PermitReportQuery
{
    public function filters(array $input): array
    {
        return [
            'status' => $this->nullableString($input['status'] ?? null),
            'qr_status' => $this->nullableString($input['qr_status'] ?? null),
            'permit_color' => $this->nullableString($input['permit_color'] ?? null),
            'parking_location_id' => $input['parking_location_id'] ?? null,
            'source' => $this->nullableString($input['source'] ?? null),
            'review_status' => $this->nullableString($input['review_status'] ?? null),
            'search' => $this->nullableString($input['search'] ?? null),
        ];
    }

    public function query(array $filters)
    {
        $filters = $this->filters($filters);

        $query = VehiclePermit::query()
            ->with([
                'employee',
                'vehicle',
                'parkingLocation',
                'reviewer',
                'activeToken' => function ($query) {
                    $query->select([
                        'permit_tokens.id',
                        'permit_tokens.vehicle_permit_id',
                        'permit_tokens.status',
                        'permit_tokens.expires_at',
                        'permit_tokens.revoked_at',
                    ]);
                },
                'latestToken' => function ($query) {
                    $query->select([
                        'permit_tokens.id',
                        'permit_tokens.vehicle_permit_id',
                        'permit_tokens.status',
                        'permit_tokens.expires_at',
                        'permit_tokens.revoked_at',
                    ]);
                },
            ])
            ->withCount('routeSegments')
            ->orderByDesc('vehicle_permits.created_at')
            ->orderByDesc('vehicle_permits.id');

        $this->applyFilters($query, $filters);

        return $query;
    }

    public function qrStatusValue(VehiclePermit $permit): string
    {
        $activeToken = $permit->activeToken;
        $latestToken = $permit->latestToken;

        if ($activeToken && $activeToken->expires_at && $activeToken->expires_at->isPast()) {
            return 'expired';
        }

        if ($activeToken) {
            return 'active';
        }

        if ($latestToken && $latestToken->status === PermitToken::STATUS_REVOKED) {
            return 'revoked';
        }

        return 'missing';
    }

    public function qrStatusLabel(VehiclePermit $permit): string
    {
        $value = $this->qrStatusValue($permit);

        return $this->qrStatusOptions()[$value] ?? $value;
    }

    public function statusOptions(): array
    {
        return [
            VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review',
            VehiclePermit::STATUS_ACTIVE => 'Aktif',
            VehiclePermit::STATUS_DRAFT => 'Draft',
            VehiclePermit::STATUS_SUSPENDED => 'Ditangguhkan',
            VehiclePermit::STATUS_EXPIRED => 'Kadaluwarsa',
            VehiclePermit::STATUS_REVOKED => 'Dicabut',
        ];
    }

    public function qrStatusOptions(): array
    {
        return [
            'missing' => 'Belum dibuat',
            'active' => 'QR Aktif',
            'expired' => 'QR Kadaluwarsa',
            'revoked' => 'QR Dicabut',
        ];
    }

    public function reviewStatusOptions(): array
    {
        return [
            'pending' => 'Belum direview',
            'reviewed' => 'Sudah direview',
        ];
    }

    public function colorOptions(): array
    {
        return VehiclePermit::query()
            ->whereNotNull('permit_color')
            ->where('permit_color', '!=', '')
            ->orderBy('permit_color')
            ->distinct()
            ->pluck('permit_color', 'permit_color')
            ->all();
    }

    public function sourceOptions(): array
    {
        return VehiclePermit::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->orderBy('source')
            ->distinct()
            ->pluck('source', 'source')
            ->all();
    }

    public function statusSummary(): array
    {
        return VehiclePermit::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['status']) {
            $query->where('vehicle_permits.status', $filters['status']);
        }

        if ($filters['permit_color']) {
            $query->where('vehicle_permits.permit_color', $filters['permit_color']);
        }

        if ($filters['parking_location_id']) {
            $query->where('vehicle_permits.parking_location_id', $filters['parking_location_id']);
        }

        if ($filters['source']) {
            $query->where('vehicle_permits.source', $filters['source']);
        }

        if ($filters['review_status'] === 'reviewed') {
            $query->whereNotNull('vehicle_permits.reviewed_at');
        }

        if ($filters['review_status'] === 'pending') {
            $query->whereNull('vehicle_permits.reviewed_at');
        }

        if ($filters['search']) {
            $this->applySearchFilter($query, $filters['search']);
        }

        if ($filters['qr_status']) {
            $this->applyQrStatusFilter($query, $filters['qr_status']);
        }
    }

    private function applySearchFilter($query, string $search): void
    {
        $query->where(function ($subQuery) use ($search) {
            $subQuery->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('nik', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            })->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                $vehicleQuery->where('plate_number', 'like', '%' . $search . '%');
            });
        });
    }

    private function applyQrStatusFilter($query, string $qrStatus): void
    {
        if ($qrStatus === 'active') {
            $query->whereHas('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_ACTIVE)
                    ->where(function ($dateQuery) {
                        $dateQuery->whereNull('expires_at')
                            ->orWhere('expires_at', '>=', now());
                    });
            });
        }

        if ($qrStatus === 'expired') {
            $query->whereHas('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_ACTIVE)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            });
        }

        if ($qrStatus === 'missing') {
            $query->whereDoesntHave('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_ACTIVE);
            })->whereDoesntHave('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_REVOKED);
            });
        }

        if ($qrStatus === 'revoked') {
            $query->whereDoesntHave('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_ACTIVE);
            })->whereHas('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_REVOKED);
            });
        }
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
