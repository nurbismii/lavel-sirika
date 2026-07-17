<?php

namespace Tests\Feature;

use App\Models\Employee;
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

        $this->assertNotEmpty(route('permits.edit', $permit));
        $this->assertTrue($admin->canAccessRoute('permits.edit'));
        $this->assertTrue($admin->canAccessRoute('permits.update'));

        $this->actingAs($auditor)
            ->get(route('permits.edit', $permit))
            ->assertForbidden();
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
}
