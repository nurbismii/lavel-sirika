<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitScanService;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitScanServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createPermit($status = VehiclePermit::STATUS_ACTIVE)
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'SECURITY TEST USER',
            'department' => 'GA',
            'position' => 'STAFF',
            'contact_number' => '08123456789',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT ' . random_int(1000, 9999) . ' XY',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $parking = ParkingLocation::create([
            'code' => 'GA-MES1-P01-' . uniqid(),
            'name' => 'GA-MES1-P01',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'manual',
            'route_raw' => 'Y1-D2',
        ]);
    }

    private function securityUser()
    {
        return User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function scan_service_accepts_valid_token_and_logs_valid_result()
    {
        $permit = $this->createPermit();
        $tokenResult = app(PermitTokenService::class)->generateForPermit($permit);

        $result = app(PermitScanService::class)->scan($tokenResult['plain_token'], $this->securityUser(), [
            'ip_address' => '127.0.0.1',
            'device_info' => 'Feature test',
        ]);

        $this->assertSame(ScanLog::RESULT_VALID, $result['result']);
        $this->assertSame($permit->id, $result['scan_log']->permit_id);
        $this->assertSame('SECURITY TEST USER', $result['permit']['employee_name']);
        $this->assertArrayNotHasKey('contact_number', $result['permit']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_VALID,
        ]);
    }

    /** @test */
    public function scan_service_returns_expired_with_limited_detail_and_logs_it()
    {
        $permit = $this->createPermit();
        $tokenResult = app(PermitTokenService::class)->generateForPermit($permit);
        $tokenResult['permit_token']->update(['expires_at' => now()->subMinute()]);

        $result = app(PermitScanService::class)->scan($tokenResult['plain_token'], $this->securityUser());

        $this->assertSame(ScanLog::RESULT_EXPIRED, $result['result']);
        $this->assertSame('SECURITY TEST USER', $result['permit']['employee_name']);
        $this->assertArrayHasKey('plate_number', $result['permit']);
        $this->assertArrayHasKey('parking_code', $result['permit']);
        $this->assertArrayNotHasKey('nik', $result['permit']);
        $this->assertArrayNotHasKey('route_raw', $result['permit']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_EXPIRED,
        ]);
    }

    /** @test */
    public function scan_service_returns_revoked_for_revoked_token()
    {
        $permit = $this->createPermit();
        $tokenResult = app(PermitTokenService::class)->generateForPermit($permit);
        $tokenResult['permit_token']->update([
            'status' => PermitToken::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        $result = app(PermitScanService::class)->scan($tokenResult['plain_token'], $this->securityUser());

        $this->assertSame(ScanLog::RESULT_REVOKED, $result['result']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_REVOKED,
        ]);
    }

    /** @test */
    public function scan_service_returns_inactive_when_permit_is_not_active()
    {
        $permit = $this->createPermit();
        $tokenResult = app(PermitTokenService::class)->generateForPermit($permit);
        $permit->update(['status' => VehiclePermit::STATUS_SUSPENDED]);

        $result = app(PermitScanService::class)->scan($tokenResult['plain_token'], $this->securityUser());

        $this->assertSame(ScanLog::RESULT_INACTIVE, $result['result']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_INACTIVE,
        ]);
    }

    /** @test */
    public function scan_service_logs_invalid_token_without_permit_id()
    {
        $result = app(PermitScanService::class)->scan('not-a-known-token', $this->securityUser());

        $this->assertSame(ScanLog::RESULT_INVALID, $result['result']);
        $this->assertNull($result['permit']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => null,
            'result' => ScanLog::RESULT_INVALID,
        ]);
    }
}
