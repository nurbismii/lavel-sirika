<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitReviewMetadataTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function vehicle_permit_stores_review_metadata_and_reviewer_relation()
    {
        $reviewer = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create([
            'nik' => '15090001',
            'name' => 'REVIEW META USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 9001 RM',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'source' => 'import',
        ]);

        $reviewedAt = now()->subMinute()->startOfSecond();

        $permit->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $reviewedAt,
            'review_note' => 'Rute dan parkir sudah diverifikasi.',
        ]);

        $permit->refresh();

        $this->assertSame($reviewer->id, $permit->reviewer->id);
        $this->assertSame('Rute dan parkir sudah diverifikasi.', $permit->review_note);
        $this->assertTrue($permit->reviewed_at->equalTo($reviewedAt));
    }
}
