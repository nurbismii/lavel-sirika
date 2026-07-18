<?php

namespace App\Services\Permits;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\PermitRouteSegment;
use App\Models\RoadSegment;
use App\Models\ScanLog;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PermitLifecycleService
{
    private $tokens;

    public function __construct(PermitTokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function revoke(VehiclePermit $permit): void
    {
        DB::transaction(function () use ($permit) {
            $lockedPermit = $this->lockPermit($permit);

            $this->revokeLockedPermit($lockedPermit);
        });
    }

    public function destroy(VehiclePermit $permit): void
    {
        DB::transaction(function () use ($permit) {
            $lockedPermit = $this->lockPermit($permit);

            $this->destroyLockedPermit($lockedPermit);
        });
    }

    public function clearAll(): int
    {
        return DB::transaction(function () {
            $permits = VehiclePermit::query()
                ->lockForUpdate()
                ->get();

            foreach ($permits as $permit) {
                if ($permit->status === VehiclePermit::STATUS_ACTIVE) {
                    $this->revokeLockedPermit($permit);
                }

                $this->destroyLockedPermit($permit);
            }

            return $permits->count();
        });
    }

    public function reactivate(VehiclePermit $permit): void
    {
        DB::transaction(function () use ($permit) {
            $lockedPermit = $this->lockPermit($permit);

            if ($lockedPermit->status !== VehiclePermit::STATUS_REVOKED) {
                throw new InvalidArgumentException('Hanya izin yang dicabut yang dapat diaktifkan kembali.');
            }

            $this->ensureSavedDataCanBeReactivated($lockedPermit);

            $hasActiveToken = PermitToken::where('vehicle_permit_id', $lockedPermit->id)
                ->where('status', PermitToken::STATUS_ACTIVE)
                ->lockForUpdate()
                ->exists();

            if ($hasActiveToken) {
                throw new InvalidArgumentException('Reaktivasi gagal karena izin masih memiliki QR aktif.');
            }

            $lockedPermit->update([
                'status' => VehiclePermit::STATUS_ACTIVE,
            ]);

            $this->tokens->generateForPermit($lockedPermit);
        });
    }

    private function ensureSavedDataCanBeReactivated(VehiclePermit $permit): void
    {
        $employeeIsActive = Employee::whereKey($permit->employee_id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->exists();
        $vehicleIsActiveForEmployee = Vehicle::whereKey($permit->vehicle_id)
            ->where('employee_id', $permit->employee_id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->exists();

        if (! $employeeIsActive || ! $vehicleIsActiveForEmployee) {
            throw new InvalidArgumentException('Reaktivasi gagal karena data karyawan atau kendaraan tidak aktif.');
        }

        $parkingIds = DB::table('vehicle_permit_parking_locations')
            ->where('vehicle_permit_id', $permit->id)
            ->lockForUpdate()
            ->pluck('parking_location_id')
            ->all();

        if (empty($parkingIds) && $permit->parking_location_id) {
            $parkingIds = [$permit->parking_location_id];
        }

        $hasActiveSavedParking = ! empty($parkingIds)
            && ParkingLocation::whereIn('id', $parkingIds)
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists();

        if (! $hasActiveSavedParking) {
            throw new InvalidArgumentException('Reaktivasi gagal karena tidak ada lokasi parkir tersimpan yang aktif.');
        }

        $routeSegmentIds = PermitRouteSegment::where('vehicle_permit_id', $permit->id)
            ->lockForUpdate()
            ->pluck('road_segment_id')
            ->all();

        $activeRouteSegmentCount = RoadSegment::whereIn('id', $routeSegmentIds)
            ->where('status', RoadSegment::STATUS_ACTIVE)
            ->lockForUpdate()
            ->count();

        if (count($routeSegmentIds) !== $activeRouteSegmentCount) {
            throw new InvalidArgumentException('Reaktivasi gagal karena terdapat segmen rute tersimpan yang tidak aktif.');
        }
    }

    private function lockPermit(VehiclePermit $permit): VehiclePermit
    {
        return VehiclePermit::whereKey($permit->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function revokeLockedPermit(VehiclePermit $permit): void
    {
        if ($permit->status !== VehiclePermit::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Hanya izin aktif yang dapat dicabut.');
        }

        PermitToken::where('vehicle_permit_id', $permit->id)
            ->where('status', PermitToken::STATUS_ACTIVE)
            ->update([
                'status' => PermitToken::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

        $permit->update([
            'status' => VehiclePermit::STATUS_REVOKED,
        ]);
    }

    private function destroyLockedPermit(VehiclePermit $permit): void
    {
        if ($permit->status === VehiclePermit::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Izin aktif harus dicabut terlebih dahulu sebelum dihapus permanen.');
        }

        ScanLog::where('permit_id', $permit->id)
            ->update(['permit_id' => null]);

        $permit->delete();
    }
}
