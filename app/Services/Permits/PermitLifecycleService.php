<?php

namespace App\Services\Permits;

use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\VehiclePermit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PermitLifecycleService
{
    public function revoke(VehiclePermit $permit): void
    {
        DB::transaction(function () use ($permit) {
            $lockedPermit = $this->lockPermit($permit);

            if ($lockedPermit->status !== VehiclePermit::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Hanya izin aktif yang dapat dicabut.');
            }

            PermitToken::where('vehicle_permit_id', $lockedPermit->id)
                ->where('status', PermitToken::STATUS_ACTIVE)
                ->update([
                    'status' => PermitToken::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);

            $lockedPermit->update([
                'status' => VehiclePermit::STATUS_REVOKED,
            ]);
        });
    }

    public function destroy(VehiclePermit $permit): void
    {
        DB::transaction(function () use ($permit) {
            $lockedPermit = $this->lockPermit($permit);

            if ($lockedPermit->status === VehiclePermit::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Izin aktif harus dicabut terlebih dahulu sebelum dihapus permanen.');
            }

            ScanLog::where('permit_id', $lockedPermit->id)
                ->update(['permit_id' => null]);

            $lockedPermit->delete();
        });
    }

    private function lockPermit(VehiclePermit $permit): VehiclePermit
    {
        return VehiclePermit::whereKey($permit->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
