<?php

namespace App\Services\Permits;

use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\VehiclePermit;
use App\Services\Imports\RouteSegmentParser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PermitReviewService
{
    private RouteSegmentParser $routeParser;

    public function __construct(RouteSegmentParser $routeParser)
    {
        $this->routeParser = $routeParser;
    }

    public function saveDraft(VehiclePermit $permit, array $data): VehiclePermit
    {
        return DB::transaction(function () use ($permit, $data) {
            $lockedPermit = $this->lockPermit($permit);
            $this->ensureNeedsReview($lockedPermit);

            $lockedPermit->update([
                'parking_location_id' => $data['parking_location_id'] ?? null,
                'route_raw' => $this->cleanText($data['route_raw'] ?? null),
                'review_note' => $this->cleanText($data['review_note'] ?? null),
            ]);

            return $lockedPermit->fresh(['employee', 'vehicle', 'parkingLocation', 'routeSegments', 'reviewer']);
        });
    }

    public function activate(VehiclePermit $permit, array $data, User $reviewer): VehiclePermit
    {
        return DB::transaction(function () use ($permit, $data, $reviewer) {
            $lockedPermit = $this->lockPermit($permit);
            $this->ensureNeedsReview($lockedPermit);
            $this->ensurePermitHasCoreRelations($lockedPermit);

            $parking = $this->resolveParkingLocation($data['parking_location_id'] ?? null);
            $routeRaw = $this->cleanText($data['route_raw'] ?? null);
            $reviewNote = $this->cleanText($data['review_note'] ?? null);

            if ($reviewNote === null) {
                throw new InvalidArgumentException('Catatan review wajib diisi sebelum aktivasi izin.');
            }

            $codes = $this->resolveRouteCodes($routeRaw);
            $segments = $this->loadRouteSegments($codes);

            $this->ensureNoOtherActivePermit($lockedPermit);

            $lockedPermit->update([
                'parking_location_id' => $parking->id,
                'route_raw' => $routeRaw,
                'review_note' => $reviewNote,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'status' => VehiclePermit::STATUS_ACTIVE,
            ]);

            $lockedPermit->permitRouteSegments()->delete();

            $sequence = 1;
            foreach ($codes as $code) {
                $lockedPermit->permitRouteSegments()->create([
                    'road_segment_id' => $segments[$code]->id,
                    'sequence' => $sequence,
                ]);

                $sequence++;
            }

            return $lockedPermit->fresh(['employee', 'vehicle', 'parkingLocation', 'permitRouteSegments', 'routeSegments', 'reviewer']);
        });
    }

    private function lockPermit(VehiclePermit $permit): VehiclePermit
    {
        return VehiclePermit::query()
            ->whereKey($permit->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureNeedsReview(VehiclePermit $permit): void
    {
        if ($permit->status !== VehiclePermit::STATUS_NEEDS_REVIEW) {
            throw new InvalidArgumentException('Izin ini tidak berada dalam status needs_review.');
        }
    }

    private function ensurePermitHasCoreRelations(VehiclePermit $permit): void
    {
        if (! $permit->employee_id) {
            throw new InvalidArgumentException('Data karyawan izin tidak valid.');
        }

        if (! $permit->vehicle_id) {
            throw new InvalidArgumentException('Data kendaraan izin tidak valid.');
        }
    }

    private function resolveParkingLocation($parkingLocationId): ParkingLocation
    {
        if (! $parkingLocationId) {
            throw new InvalidArgumentException('Pilih lokasi parkir sebelum aktivasi izin.');
        }

        $parking = ParkingLocation::query()
            ->whereKey($parkingLocationId)
            ->where('status', 'active')
            ->first();

        if (! $parking) {
            throw new InvalidArgumentException('Pilih lokasi parkir sebelum aktivasi izin.');
        }

        return $parking;
    }

    private function resolveRouteCodes(?string $routeRaw): array
    {
        if ($routeRaw === null) {
            throw new InvalidArgumentException('Rute kendaraan kosong.');
        }

        $activeCodes = RoadSegment::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $parsed = $this->routeParser->parse($routeRaw, $activeCodes);

        if (($parsed['codes'] ?? []) === []) {
            $warnings = $parsed['warnings'] ?? [];

            if (in_array('Rute kendaraan kosong', $warnings, true)) {
                throw new InvalidArgumentException('Rute kendaraan kosong.');
            }

            throw new InvalidArgumentException('Rute tidak mengandung kode segmen resmi.');
        }

        $warnings = $parsed['warnings'] ?? [];
        if ($warnings !== []) {
            throw new InvalidArgumentException($warnings[0]);
        }

        return array_values($parsed['codes']);
    }

    private function loadRouteSegments(array $codes): array
    {
        $segments = RoadSegment::query()
            ->where('status', 'active')
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        foreach ($codes as $code) {
            if (! $segments->has($code)) {
                throw new InvalidArgumentException('Kode segmen rute tidak ditemukan di master aktif: ' . $code . '.');
            }
        }

        return $segments->all();
    }

    private function ensureNoOtherActivePermit(VehiclePermit $permit): void
    {
        $existingPermit = VehiclePermit::query()
            ->where('vehicle_id', $permit->vehicle_id)
            ->where('status', VehiclePermit::STATUS_ACTIVE)
            ->where('id', '!=', $permit->id)
            ->lockForUpdate()
            ->first();

        if ($existingPermit) {
            throw new InvalidArgumentException('Kendaraan ini masih memiliki izin aktif lain. Nonaktifkan izin lama sebelum aktivasi.');
        }
    }

    private function cleanText($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
