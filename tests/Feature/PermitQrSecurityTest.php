<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitScanService;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitQrSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function generated_qr_plaintext_is_only_persisted_as_a_sha256_hash()
    {
        $permit = $this->activePermit();
        $result = app(PermitTokenService::class)->generateForPermit($permit);
        $token = $result['permit_token']->fresh();

        $this->assertSame(64, strlen($result['plain_token']));
        $this->assertSame(hash('sha256', $result['plain_token']), $token->token_hash);
        $this->assertNotSame($result['plain_token'], $token->token_hash);
        $this->assertStringNotContainsString('/scan', $result['plain_token']);
        $this->assertStringNotContainsString('signature=', $result['plain_token']);
    }

    /** @test */
    public function invalid_scan_does_not_reveal_permit_data()
    {
        $scanner = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $result = app(PermitScanService::class)->scan(str_repeat('x', 64), $scanner);

        $this->assertSame('invalid', $result['result']);
        $this->assertNull($result['permit']);
        $this->assertSame('QR tidak dikenal.', $result['message']);
    }

    private function activePermit()
    {
        $employee = Employee::create(['nik' => 'SEC-001', 'name' => 'Security Test']);
        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 7001 SEC',
        ]);
        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);
    }
}
