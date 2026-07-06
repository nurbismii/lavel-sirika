<?php

namespace App\Services\Permits;

use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\VehiclePermit;
use App\Services\Routes\PermitRouteMapService;
use Throwable;

class PermitScanService
{
    private PermitRouteMapService $routeMaps;

    public function __construct(PermitRouteMapService $routeMaps)
    {
        $this->routeMaps = $routeMaps;
    }

    public function scan(string $plainToken, ?User $scanner, array $context = []): array
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '') {
            return $this->logAndReturn(
                ScanLog::RESULT_INVALID,
                null,
                $scanner,
                $context,
                'QR tidak dikenal.',
                null
            );
        }

        $token = PermitToken::with([
            'permit.employee',
            'permit.vehicle',
            'permit.parkingLocation',
            'permit.routeSegments',
        ])
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token) {
            return $this->logAndReturn(
                ScanLog::RESULT_INVALID,
                null,
                $scanner,
                $context,
                'QR tidak dikenal.',
                null
            );
        }

        $permit = $token->permit;

        if ($token->status === PermitToken::STATUS_REVOKED) {
            return $this->logAndReturn(
                ScanLog::RESULT_REVOKED,
                $permit,
                $scanner,
                $context,
                'QR telah dicabut.',
                null
            );
        }

        if ($this->tokenExpired($token) || $this->permitDateExpired($permit)) {
            return $this->logAndReturn(
                ScanLog::RESULT_EXPIRED,
                $permit,
                $scanner,
                $context,
                'QR kadaluwarsa.',
                $this->limitedPermitData($permit)
            );
        }

        if (! $permit || $permit->status !== VehiclePermit::STATUS_ACTIVE) {
            return $this->logAndReturn(
                ScanLog::RESULT_INACTIVE,
                $permit,
                $scanner,
                $context,
                'Izin tidak aktif.',
                null
            );
        }

        return $this->logAndReturn(
            ScanLog::RESULT_VALID,
            $permit,
            $scanner,
            $context,
            'QR valid.',
            $this->fullPermitData($permit)
        );
    }

    private function logAndReturn(
        string $result,
        ?VehiclePermit $permit,
        ?User $scanner,
        array $context,
        string $message,
        ?array $permitData
    ): array {
        $scanLog = ScanLog::create([
            'permit_id' => $permit ? $permit->id : null,
            'scanned_by' => $scanner ? $scanner->id : null,
            'scanned_at' => now(),
            'result' => $result,
            'device_info' => $context['device_info'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'notes' => $message,
        ]);

        return [
            'result' => $result,
            'message' => $message,
            'permit' => $permitData,
            'scan_log' => $scanLog,
        ];
    }

    private function tokenExpired(PermitToken $token): bool
    {
        return $token->expires_at !== null && $token->expires_at->isPast();
    }

    private function permitDateExpired(?VehiclePermit $permit): bool
    {
        return $permit !== null
            && $permit->valid_until !== null
            && $permit->valid_until->isPast();
    }

    private function limitedPermitData(?VehiclePermit $permit): ?array
    {
        if (! $permit) {
            return null;
        }

        return [
            'employee_name' => optional($permit->employee)->name,
            'plate_number' => optional($permit->vehicle)->plate_number,
            'parking_code' => optional($permit->parkingLocation)->code,
        ];
    }

    private function fullPermitData(VehiclePermit $permit): array
    {
        $data = [
            'employee_name' => optional($permit->employee)->name,
            'plate_number' => optional($permit->vehicle)->plate_number,
            'parking_code' => optional($permit->parkingLocation)->code,
            'permit_color' => $permit->permit_color,
            'status' => $permit->status,
            'route_raw' => $permit->route_raw,
        ];

        try {
            $data['route_map'] = $this->routeMaps->forPermit($permit);
        } catch (Throwable $exception) {
            $data['route_map_warning'] = 'Peta rute tidak tersedia.';
        }

        return $data;
    }
}
