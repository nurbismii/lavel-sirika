<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitQrServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function permit_token_and_scan_log_constants_and_relationships_are_available()
    {
        $employee = Employee::create([
            'nik' => 'EMP-001',
            'name' => 'TEST USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 1001 AA',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);

        PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', 'old-token'),
            'status' => PermitToken::STATUS_REVOKED,
            'expires_at' => now()->addDay(),
            'revoked_at' => now(),
        ]);

        $activeToken = PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', 'active-token'),
            'status' => PermitToken::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $this->assertSame('active', PermitToken::STATUS_ACTIVE);
        $this->assertSame('revoked', PermitToken::STATUS_REVOKED);
        $this->assertSame('valid', ScanLog::RESULT_VALID);
        $this->assertSame('expired', ScanLog::RESULT_EXPIRED);
        $this->assertSame('revoked', ScanLog::RESULT_REVOKED);
        $this->assertSame('inactive', ScanLog::RESULT_INACTIVE);
        $this->assertSame('invalid', ScanLog::RESULT_INVALID);
        $this->assertSame($activeToken->id, $permit->fresh()->activeToken->id);
        $this->assertSame($activeToken->id, $permit->fresh()->latestToken->id);
    }
}
