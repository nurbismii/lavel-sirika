<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitQrServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createPermit($status = VehiclePermit::STATUS_ACTIVE)
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'TEST USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT ' . random_int(1000, 9999) . ' AA',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'manual',
        ]);
    }

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

    /** @test */
    public function token_service_generates_hash_only_token_and_qr_svg_for_active_permit()
    {
        $permit = $this->createPermit();
        $service = app(PermitTokenService::class);

        $result = $service->generateForPermit($permit);

        $this->assertArrayHasKey('plain_token', $result);
        $this->assertArrayHasKey('permit_token', $result);
        $this->assertArrayHasKey('qr_svg', $result);
        $this->assertSame(hash('sha256', $result['plain_token']), $result['permit_token']->token_hash);
        $this->assertDatabaseMissing('permit_tokens', ['token_hash' => $result['plain_token']]);
        $this->assertStringContainsString('<svg', $result['qr_svg']);
        $this->assertTrue($result['permit_token']->expires_at->isSameDay(now()->addYear()));
    }

    /** @test */
    public function token_service_refuses_non_active_permit()
    {
        $permit = $this->createPermit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $service = app(PermitTokenService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('QR hanya dapat dibuat untuk izin aktif.');

        $service->generateForPermit($permit);
    }

    /** @test */
    public function token_service_does_not_create_duplicate_active_token()
    {
        $permit = $this->createPermit();
        $service = app(PermitTokenService::class);

        $first = $service->generateForPermit($permit);

        try {
            $service->generateForPermit($permit->fresh());
            $this->fail('Expected duplicate token generation to throw an InvalidArgumentException.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                'QR aktif sudah tersedia. Gunakan renew untuk membuat QR baru.',
                $exception->getMessage()
            );
        }

        $this->assertSame(PermitToken::STATUS_ACTIVE, $first['permit_token']->fresh()->status);
        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());
    }

    /** @test */
    public function token_service_generates_separate_qrs_for_active_permits_of_different_employees_sharing_a_vehicle()
    {
        $firstPermit = $this->createPermit();
        $secondEmployee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'SHARED QR USER',
            'status' => 'active',
        ]);
        $secondPermit = VehiclePermit::create([
            'employee_id' => $secondEmployee->id,
            'vehicle_id' => $firstPermit->vehicle_id,
            'permit_color' => 'merah',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);

        $service = app(PermitTokenService::class);
        $firstToken = $service->generateForPermit($firstPermit);
        $secondToken = $service->generateForPermit($secondPermit);

        $this->assertNotSame($firstToken['plain_token'], $secondToken['plain_token']);
        $this->assertNotSame($firstToken['permit_token']->id, $secondToken['permit_token']->id);
        $this->assertSame($firstPermit->id, $firstToken['permit_token']->vehicle_permit_id);
        $this->assertSame($secondPermit->id, $secondToken['permit_token']->vehicle_permit_id);
        $this->assertSame(2, PermitToken::whereIn('vehicle_permit_id', [$firstPermit->id, $secondPermit->id])
            ->where('status', PermitToken::STATUS_ACTIVE)
            ->count());
    }

    /** @test */
    public function token_service_renew_revokes_old_token_and_creates_new_one_year_token()
    {
        $permit = $this->createPermit();
        $service = app(PermitTokenService::class);

        $old = $service->generateForPermit($permit);
        $new = $service->renewForPermit($permit->fresh());

        $this->assertNotSame($old['permit_token']->id, $new['permit_token']->id);
        $this->assertSame(PermitToken::STATUS_REVOKED, $old['permit_token']->fresh()->status);
        $this->assertNotNull($old['permit_token']->fresh()->revoked_at);
        $this->assertSame(PermitToken::STATUS_ACTIVE, $new['permit_token']->status);
        $this->assertTrue($new['permit_token']->expires_at->isSameDay(now()->addYear()));
    }

    /** @test */
    public function token_service_bulk_generates_only_active_permits_without_active_token()
    {
        $firstActive = $this->createPermit();
        $secondActive = $this->createPermit();
        $needsReview = $this->createPermit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $service = app(PermitTokenService::class);

        $service->generateForPermit($secondActive);
        $summary = $service->bulkGenerateForActivePermits();

        $this->assertSame(1, $summary['created']);
        $this->assertSame(2, $summary['skipped']);
        $this->assertNotNull($firstActive->fresh()->activeToken);
        $this->assertNotNull($secondActive->fresh()->activeToken);
        $this->assertNull($needsReview->fresh()->activeToken);
    }
}
