<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitScanService;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermitQrSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function generated_qr_plaintext_is_persisted_as_a_hash_and_encrypted_ciphertext()
    {
        $permit = $this->activePermit();
        $result = app(PermitTokenService::class)->generateForPermit($permit);
        $token = $result['permit_token']->fresh();

        $this->assertSame(64, strlen($result['plain_token']));
        $this->assertSame(hash('sha256', $result['plain_token']), $token->token_hash);
        $this->assertNotSame($result['plain_token'], $token->token_hash);
        $this->assertNotNull($token->token_encrypted);
        $this->assertNotSame($result['plain_token'], $token->token_encrypted);
        $this->assertSame($result['plain_token'], Crypt::decryptString($token->token_encrypted));
        $this->assertStringNotContainsString('/scan', $result['plain_token']);
        $this->assertStringNotContainsString('signature=', $result['plain_token']);
    }

    /** @test */
    public function invalid_scan_does_not_reveal_permit_data()
    {
        $permit = $this->activePermit();
        $validToken = app(PermitTokenService::class)->generateForPermit($permit)['plain_token'];
        $randomToken = Str::random(64);

        $this->assertNotSame($validToken, $randomToken);

        $scanner = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $result = app(PermitScanService::class)->scan($randomToken, $scanner);

        $this->assertSame('invalid', $result['result']);
        $this->assertNull($result['permit']);
        $this->assertSame('QR tidak dikenal.', $result['message']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => null,
            'result' => 'invalid',
        ]);
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
