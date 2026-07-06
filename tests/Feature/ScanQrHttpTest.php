<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanQrHttpTest extends TestCase
{
    use RefreshDatabase;

    private function security()
    {
        return User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit()
    {
        $employee = Employee::create([
            'nik' => 'EMP-SCAN',
            'name' => 'SCAN HTTP USER',
            'contact_number' => '08123456789',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 9001 SC',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'merah',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);
    }

    /** @test */
    public function security_can_verify_valid_token_via_http_and_scan_is_logged()
    {
        $permit = $this->permit();
        $token = app(PermitTokenService::class)->generateForPermit($permit);

        $response = $this->actingAs($this->security())
            ->postJson(route('scan.verify'), [
                'token' => $token['plain_token'],
                'device_info' => 'Browser test',
            ])
            ->assertOk()
            ->assertJsonPath('result', ScanLog::RESULT_VALID)
            ->assertJsonPath('permit.employee_name', 'SCAN HTTP USER');

        $this->assertArrayNotHasKey('contact_number', $response->json('permit'));

        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_VALID,
            'device_info' => 'Browser test',
        ]);
    }

    /** @test */
    public function security_can_verify_invalid_token_and_it_is_logged()
    {
        $this->actingAs($this->security())
            ->postJson(route('scan.verify'), [
                'token' => 'not-a-valid-token-for-sirika',
            ])
            ->assertOk()
            ->assertJsonPath('result', ScanLog::RESULT_INVALID)
            ->assertJsonPath('permit', null);

        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => null,
            'result' => ScanLog::RESULT_INVALID,
        ]);
    }

    /** @test */
    public function auditor_cannot_verify_scan_token()
    {
        $auditor = User::factory()->create([
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($auditor)
            ->postJson(route('scan.verify'), ['token' => 'anything'])
            ->assertForbidden();
    }
}
