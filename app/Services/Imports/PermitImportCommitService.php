<?php

namespace App\Services\Imports;

use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PermitImportCommitService
{
    public function commit(ImportBatch $batch): ImportBatch
    {
        return DB::transaction(function () use ($batch) {
            /** @var \App\Models\ImportBatch $lockedBatch */
            $lockedBatch = ImportBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBatch->status === ImportBatch::STATUS_COMMITTED) {
                throw new RuntimeException('Batch sudah pernah dikomit.');
            }

            if ($lockedBatch->status !== ImportBatch::STATUS_PREVIEWED) {
                throw new RuntimeException('Batch belum siap dikomit.');
            }

            $rows = $lockedBatch->rows()
                ->whereIn('status', [ImportRow::STATUS_VALID, ImportRow::STATUS_NEEDS_REVIEW])
                ->orderBy('row_number')
                ->get();

            foreach ($rows as $row) {
                $this->commitRow($lockedBatch, $row);
            }

            $lockedBatch->update([
                'status' => ImportBatch::STATUS_COMMITTED,
            ]);

            return $lockedBatch->fresh();
        });
    }

    private function commitRow(ImportBatch $batch, ImportRow $row): void
    {
        $data = $row->normalized_data ?: [];

        if (! $this->hasMinimumData($data)) {
            return;
        }

        $employee = Employee::query()->firstOrCreate(
            ['nik' => $data['nik']],
            [
                'name' => $data['employee_name'],
                'department' => $data['department'] ?? null,
                'section' => $data['section'] ?? null,
                'position' => $data['position'] ?? null,
                'division' => $data['division'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'status' => 'active',
            ]
        );

        $vehicle = Vehicle::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'plate_number' => $data['plate_number'],
            ],
            [
                'vehicle_type' => 'motorcycle',
                'status' => 'active',
            ]
        );

        $parkingLocation = null;
        if (! empty($data['parking_location_code'])) {
            $parkingLocation = ParkingLocation::query()->firstOrCreate(
                ['code' => $data['parking_location_code']],
                [
                    'name' => $data['parking_location_code'],
                    'status' => 'active',
                ]
            );
        }

        $permitStatus = $row->status === ImportRow::STATUS_VALID
            ? VehiclePermit::STATUS_ACTIVE
            : VehiclePermit::STATUS_NEEDS_REVIEW;

        $warnings = $row->warnings ?: [];
        if ($this->hasExistingActivePermit($vehicle->id)) {
            $permitStatus = VehiclePermit::STATUS_NEEDS_REVIEW;
            $warnings[] = 'Kendaraan sudah memiliki izin aktif, perlu review sebelum aktivasi.';
        }

        $permit = VehiclePermit::query()->create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parkingLocation ? $parkingLocation->id : null,
            'permit_color' => $data['permit_color'] ?? null,
            'reason' => $data['reason'] ?? null,
            'approval_status' => $data['approval_status'] ?? 'approved',
            'valid_from' => null,
            'valid_until' => null,
            'status' => $permitStatus,
            'source' => 'import',
            'source_import_id' => $batch->id,
            'route_raw' => $data['route_raw'] ?? null,
        ]);

        $this->attachRouteSegments($permit, $data['route_segment_codes'] ?? []);

        $row->update([
            'status' => ImportRow::STATUS_COMMITTED,
            'warnings' => array_values(array_unique($warnings)),
            'created_employee_id' => $employee->id,
            'created_vehicle_id' => $vehicle->id,
            'created_permit_id' => $permit->id,
        ]);
    }

    private function hasMinimumData(array $data): bool
    {
        return ! empty($data['nik'])
            && ! empty($data['employee_name'])
            && ! empty($data['plate_number']);
    }

    private function hasExistingActivePermit(int $vehicleId): bool
    {
        return VehiclePermit::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', VehiclePermit::STATUS_ACTIVE)
            ->exists();
    }

    private function attachRouteSegments(VehiclePermit $permit, array $codes): void
    {
        if ($codes === []) {
            return;
        }

        $segments = RoadSegment::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        $sequence = 1;

        foreach ($codes as $code) {
            $segment = $segments->get($code);

            if (! $segment) {
                continue;
            }

            $permit->permitRouteSegments()->create([
                'road_segment_id' => $segment->id,
                'sequence' => $sequence,
            ]);

            $sequence++;
        }
    }
}
