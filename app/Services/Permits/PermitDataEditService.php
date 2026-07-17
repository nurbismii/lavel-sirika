<?php

namespace App\Services\Permits;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitRouteSegment;
use App\Models\RoadSegment;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PermitDataEditService
{
    public function update(VehiclePermit $permit, array $data): VehiclePermit
    {
        return DB::transaction(function () use ($permit, $data) {
            $lockedPermit = VehiclePermit::query()
                ->lockForUpdate()
                ->findOrFail($permit->id);

            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($lockedPermit->employee_id);

            $vehicle = Vehicle::query()
                ->lockForUpdate()
                ->findOrFail($lockedPermit->vehicle_id);

            $parkingLocationIds = array_values($data['parking_location_ids']);
            $roadSegmentIds = array_values($data['road_segment_ids']);
            $parkingLocations = $this->activeParkingLocations($parkingLocationIds);
            $roadSegments = $this->activeRoadSegments($roadSegmentIds);

            $employee->update([
                'nik' => $data['nik'],
                'name' => $data['name'],
            ]);
            $vehicle->update(['plate_number' => $data['plate_number']]);

            $lockedPermit->update([
                'parking_location_id' => $parkingLocationIds[0],
                'route_raw' => collect($roadSegmentIds)
                    ->map(fn (int $id) => $roadSegments->get($id)->code)
                    ->implode(' -> '),
            ]);
            $lockedPermit->parkingLocations()->sync($parkingLocations->keys()->all());

            PermitRouteSegment::query()
                ->where('vehicle_permit_id', $lockedPermit->id)
                ->delete();

            foreach ($roadSegmentIds as $index => $roadSegmentId) {
                PermitRouteSegment::create([
                    'vehicle_permit_id' => $lockedPermit->id,
                    'road_segment_id' => $roadSegmentId,
                    'sequence' => $index + 1,
                ]);
            }

            return $lockedPermit->fresh();
        });
    }

    private function activeParkingLocations(array $ids)
    {
        $locations = ParkingLocation::query()
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        if ($locations->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'parking_location_ids' => 'Lokasi parkir harus aktif.',
            ]);
        }

        return $locations;
    }

    private function activeRoadSegments(array $ids)
    {
        $segments = RoadSegment::query()
            ->whereIn('id', $ids)
            ->where('status', RoadSegment::STATUS_ACTIVE)
            ->get()
            ->keyBy('id');

        if ($segments->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'road_segment_ids' => 'Segmen jalan harus aktif.',
            ]);
        }

        return $segments;
    }
}
