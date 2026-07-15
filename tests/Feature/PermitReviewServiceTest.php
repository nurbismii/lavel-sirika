<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitReviewService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class PermitReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_saves_review_draft_without_activating_the_permit()
    {
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');

        $updated = app(PermitReviewService::class)->saveDraft($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1 - D2',
            'review_note' => 'Menunggu cek akhir.',
        ]);

        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $updated->status);
        $this->assertSame($parking->id, $updated->parking_location_id);
        $this->assertSame('Y1 - D2', $updated->route_raw);
        $this->assertSame('Menunggu cek akhir.', $updated->review_note);
        $this->assertNull($updated->reviewed_by);
        $this->assertNull($updated->reviewed_at);
    }

    /** @test */
    public function it_activates_needs_review_permit_and_replaces_route_segments()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $oldSegment = $this->segment('OLD1');
        $first = $this->segment('Y1');
        $second = $this->segment('D2');

        $permit->permitRouteSegments()->create([
            'road_segment_id' => $oldSegment->id,
            'sequence' => 1,
        ]);

        $activated = app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1 -> D2',
            'review_note' => 'Rute dan parkir sudah valid.',
        ], $reviewer);

        $this->assertSame(VehiclePermit::STATUS_ACTIVE, $activated->status);
        $this->assertSame($parking->id, $activated->parking_location_id);
        $this->assertSame('Y1 -> D2', $activated->route_raw);
        $this->assertSame($reviewer->id, $activated->reviewed_by);
        $this->assertNotNull($activated->reviewed_at);
        $this->assertSame('Rute dan parkir sudah valid.', $activated->review_note);

        $this->assertSame(
            [$first->id, $second->id],
            $activated->permitRouteSegments()->orderBy('sequence')->pluck('road_segment_id')->all()
        );

        $this->assertSame(
            [1, 2],
            array_map('intval', $activated->permitRouteSegments()->orderBy('sequence')->pluck('sequence')->all())
        );
    }

    /** @test */
    public function it_blocks_activation_when_permit_is_not_needs_review()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_ACTIVE);
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Izin ini tidak berada dalam status needs_review.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_blocks_activation_when_parking_location_is_empty()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pilih lokasi parkir sebelum aktivasi izin.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => null,
            'route_raw' => 'Y1',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_blocks_activation_when_route_is_empty()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rute kendaraan kosong.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => '',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_blocks_activation_when_route_has_unknown_token()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rute mengandung token tidak dikenal: X99');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1 -> X99',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_reports_unknown_token_when_route_has_no_known_segments()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rute mengandung token tidak dikenal: X99');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'X99',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_activates_needs_review_permit_for_second_vehicle_when_first_vehicle_has_active_permit()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $this->segment('Y1');
        $firstVehicleId = $permit->vehicle_id;

        $secondVehicle = Vehicle::create([
            'employee_id' => $permit->employee_id,
            'plate_number' => 'DT ' . random_int(1000, 9999) . ' RV',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $permit->update([
            'vehicle_id' => $secondVehicle->id,
        ]);

        VehiclePermit::create([
            'employee_id' => $permit->employee_id,
            'vehicle_id' => $firstVehicleId,
            'parking_location_id' => $parking->id,
            'permit_color' => 'merah',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
            'route_raw' => 'Y1',
        ]);

        $activated = app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1',
            'review_note' => 'Valid.',
        ], $reviewer);

        $this->assertSame(VehiclePermit::STATUS_ACTIVE, $activated->status);
        $this->assertSame($secondVehicle->id, $activated->vehicle_id);
    }

    /** @test */
    public function it_locks_the_vehicle_row_before_checking_active_duplicate_permits()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $this->segment('Y1');
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1',
            'review_note' => 'Vehicle lock verified.',
        ], $reviewer);

        $vehicleQueryIndex = $this->firstQueryIndexContaining($queries, 'from "vehicles"');
        $activePermitQueryIndex = $this->firstQueryIndexContaining($queries, '"vehicle_id" = ? and "status" = ? and "id" != ?');

        $this->assertNotNull($vehicleQueryIndex, 'Expected activation to query and lock the shared vehicle row.');
        $this->assertNotNull($activePermitQueryIndex, 'Expected activation to check duplicate active permits.');
        $this->assertLessThan($activePermitQueryIndex, $vehicleQueryIndex);
    }

    /** @test */
    public function it_blocks_activation_when_review_note_is_empty()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Catatan review wajib diisi sebelum aktivasi izin.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1',
            'review_note' => '   ',
        ], $reviewer);
    }

    private function firstQueryIndexContaining(array $queries, string $needle): ?int
    {
        foreach ($queries as $index => $query) {
            if (strpos($query, $needle) !== false) {
                return $index;
            }
        }

        return null;
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function parking(string $code): ParkingLocation
    {
        return ParkingLocation::create([
            'code' => $code,
            'name' => 'Parkir ' . $code,
            'status' => 'active',
        ]);
    }

    private function segment(string $code): RoadSegment
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => 'Jalan ' . $code,
            'start_location' => 'Start ' . $code,
            'end_location' => 'End ' . $code,
            'status' => 'active',
        ]);
    }

    private function permit(string $status): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'REVIEW SERVICE USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT ' . random_int(1000, 9999) . ' RS',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'import',
            'route_raw' => null,
        ]);
    }
}
