<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitRouteSegment;
use App\Models\PermitToken;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitDataEditHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function permit_edit_routes_are_available_only_to_admin_hr()
    {
        $permit = $this->permit();
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $auditor = $this->user(User::ROLE_AUDITOR);
        $activeParking = $this->parkingLocation('P-AKTIF');
        $inactiveParking = ParkingLocation::create([
            'code' => 'P-NONAKTIF',
            'name' => 'Parkir Nonaktif',
            'status' => 'inactive',
        ]);
        $activeSegment = $this->roadSegment('JLN-AKTIF');
        $inactiveSegment = RoadSegment::create([
            'code' => 'JLN-NONAKTIF',
            'name' => 'Jalan Nonaktif',
            'status' => RoadSegment::STATUS_INACTIVE,
        ]);

        $permit->parkingLocations()->attach($activeParking);
        $permit->permitRouteSegments()->create([
            'road_segment_id' => $activeSegment->id,
            'sequence' => 1,
        ]);

        $this->assertNotEmpty(route('permits.edit', $permit));
        $this->assertTrue($admin->canAccessRoute('permits.edit'));
        $this->assertTrue($admin->canAccessRoute('permits.update'));

        $this->actingAs($admin)
            ->get(route('permits.edit', $permit))
            ->assertOk()
            ->assertViewIs('permits.edit')
            ->assertViewHas('permit', fn (VehiclePermit $viewPermit) => $viewPermit->is($permit))
            ->assertViewHas('parkingLocations', fn ($locations) => $locations->pluck('id')->all() === [$activeParking->id])
            ->assertViewHas('roadSegments', fn ($segments) => $segments->pluck('id')->all() === [$activeSegment->id]);

        $this->actingAs($auditor)
            ->get(route('permits.edit', $permit))
            ->assertForbidden();
    }

    /** @test */
    public function admin_can_update_permit_identity_parking_and_ordered_route_without_changing_status()
    {
        $permit = $this->permit();
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $originalReviewedAt = now()->subDay();
        $permit->update([
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $originalReviewedAt,
            'review_note' => 'Perlu dokumen tambahan',
        ]);
        $originalTokenHash = hash('sha512', 'permit-token-' . $permit->id);
        $originalTokenExpiresAt = now()->addYear()->startOfSecond();
        $token = PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => $originalTokenHash,
            'status' => PermitToken::STATUS_ACTIVE,
            'expires_at' => $originalTokenExpiresAt,
        ]);
        $firstParking = $this->parkingLocation('P-01');
        $secondParking = $this->parkingLocation('P-02');
        $firstSegment = $this->roadSegment('JLN-01');
        $secondSegment = $this->roadSegment('JLN-02');

        $this->actingAs($reviewer)
            ->put(route('permits.update', $permit), [
                'nik' => 'EMP-UPDATED-01',
                'name' => 'Nama Diperbarui',
                'plate_number' => 'DT 7002 PE',
                'parking_location_ids' => [$secondParking->id, $firstParking->id],
                'road_segment_ids' => [$secondSegment->id, $firstSegment->id],
            ])
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('success');

        $permit->refresh();
        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $permit->status);
        $this->assertSame($reviewer->id, $permit->reviewed_by);
        $this->assertSame(
            $originalReviewedAt->format('Y-m-d H:i:s'),
            $permit->reviewed_at->format('Y-m-d H:i:s')
        );
        $this->assertSame('Perlu dokumen tambahan', $permit->review_note);
        $this->assertDatabaseHas('permit_tokens', [
            'id' => $token->id,
            'token_hash' => $originalTokenHash,
            'status' => PermitToken::STATUS_ACTIVE,
            'expires_at' => $originalTokenExpiresAt->format('Y-m-d H:i:s'),
        ]);
        $this->assertSame($secondParking->id, $permit->parking_location_id);
        $this->assertSame('JLN-02 -> JLN-01', $permit->route_raw);
        $this->assertDatabaseHas('employees', ['id' => $permit->employee_id, 'nik' => 'EMP-UPDATED-01', 'name' => 'Nama Diperbarui']);
        $this->assertDatabaseHas('vehicles', ['id' => $permit->vehicle_id, 'plate_number' => 'DT 7002 PE']);
        $this->assertSame([$firstParking->id, $secondParking->id], $permit->parkingLocations()
            ->orderBy('parking_locations.id')
            ->pluck('parking_locations.id')
            ->map(fn ($id) => (int) $id)
            ->all());
        $this->assertSame([
            [$secondSegment->id, 1],
            [$firstSegment->id, 2],
        ], PermitRouteSegment::query()
            ->where('vehicle_permit_id', $permit->id)
            ->orderBy('sequence')
            ->get(['road_segment_id', 'sequence'])
            ->map(fn (PermitRouteSegment $route) => [$route->road_segment_id, $route->sequence])
            ->all());
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit(): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'Permit Edit User',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 6001 PE',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
        ]);
    }

    private function parkingLocation(string $code): ParkingLocation
    {
        return ParkingLocation::create([
            'code' => $code,
            'name' => 'Parkir ' . $code,
            'status' => 'active',
        ]);
    }

    private function roadSegment(string $code): RoadSegment
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => 'Jalan ' . $code,
            'status' => RoadSegment::STATUS_ACTIVE,
        ]);
    }
}
