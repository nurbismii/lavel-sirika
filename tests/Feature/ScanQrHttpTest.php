<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RoadSegment;
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
        $permit->routeSegments()->attach($this->completeSegment('Y1')->id, ['sequence' => 1]);
        $token = app(PermitTokenService::class)->generateForPermit($permit);

        $response = $this->actingAs($this->security())
            ->postJson(route('scan.verify'), [
                'token' => $token['plain_token'],
                'device_info' => 'Browser test',
            ])
            ->assertOk()
            ->assertJsonPath('result', ScanLog::RESULT_VALID)
            ->assertJsonPath('permit.employee_name', 'SCAN HTTP USER')
            ->assertJsonPath('permit.route_map.map.key', 'vdni-road-map-v1');

        $this->assertArrayNotHasKey('contact_number', $response->json('permit'));

        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_VALID,
            'device_info' => 'Browser test',
        ]);
    }

    /** @test */
    public function scan_page_wraps_permit_result_in_a_single_alpine_root()
    {
        $html = $this->actingAs($this->security())
            ->get(route('scan.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<template x-if="result\.permit">\s*<div[^>]*data-scan-permit-result/s',
            $html
        );
        $this->assertStringContainsString('x-if="result.permit.route_map"', $html);
        $this->assertStringContainsString('x-if="result.permit.route_map_warning"', $html);
    }

    /** @test */
    public function scan_page_exposes_rear_camera_default_and_switch_control()
    {
        $html = $this->actingAs($this->security())
            ->get(route('scan.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Kamera belakang digunakan sebagai default.', $html);
        $this->assertStringContainsString('x-on:click="switchCamera"', $html);
        $this->assertStringContainsString('x-text="cameraDirectionLabel"', $html);
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
    public function security_can_verify_expired_token_without_route_map()
    {
        $permit = $this->permit();
        $permit->routeSegments()->attach($this->completeSegment('Y1')->id, ['sequence' => 1]);
        $token = app(PermitTokenService::class)->generateForPermit($permit);
        $token['permit_token']->update(['expires_at' => now()->subMinute()]);

        $response = $this->actingAs($this->security())
            ->postJson(route('scan.verify'), [
                'token' => $token['plain_token'],
            ])
            ->assertOk()
            ->assertJsonPath('result', ScanLog::RESULT_EXPIRED);

        $this->assertIsArray($response->json('permit'));
        $this->assertArrayNotHasKey('route_map', $response->json('permit'));
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

    private function completeSegment(string $code): RoadSegment
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => $code,
            'start_location' => 'Start ' . $code,
            'end_location' => 'End ' . $code,
            'status' => 'active',
            'polyline_json' => [
                'version' => 1,
                'map_key' => 'vdni-road-map-v1',
                'status' => 'complete',
                'points' => [
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ],
                'updated_by' => null,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
