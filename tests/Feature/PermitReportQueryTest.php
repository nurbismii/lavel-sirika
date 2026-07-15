<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Reports\PermitReportQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitReportQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function it_filters_permits_by_status_review_status_source_color_parking_and_search()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $parking = $this->parking('P1');
        $reviewer = $this->user(User::ROLE_ADMIN_HR);

        $matching = $this->permit([
            'name' => 'MATCH REPORT USER',
            'nik' => '15090001',
            'plate' => 'DT 9001 MR',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'source' => 'import',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->permit([
            'name' => 'BLOCKED REPORT USER',
            'nik' => '15090002',
            'plate' => 'DT 9002 BR',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'permit_color' => 'merah',
            'source' => 'manual',
        ]);

        $reports = app(PermitReportQuery::class);

        $results = $reports->query($reports->filters([
            'status' => VehiclePermit::STATUS_ACTIVE,
            'review_status' => 'reviewed',
            'source' => 'import',
            'permit_color' => 'biru',
            'parking_location_id' => $parking->id,
            'search' => '9001',
        ]))->get();

        $this->assertCount(1, $results);
        $this->assertSame($matching->id, $results->first()->id);
        $this->assertSame(0, (int) $results->first()->route_segments_count);
    }

    /** @test */
    public function it_filters_and_displays_permits_by_any_selected_parking_location()
    {
        $firstParking = $this->parking('P1');
        $secondParking = $this->parking('P2');
        $permit = $this->permit(['parking_location_id' => $firstParking->id]);
        $permit->parkingLocations()->sync([$firstParking->id, $secondParking->id]);

        $results = app(PermitReportQuery::class)->query([
            'parking_location_id' => $secondParking->id,
        ])->get();

        $this->assertSame([$permit->id], $results->pluck('id')->all());
        $this->assertSame('P1, P2', $results->first()->parkingLocationCodes());
    }

    /** @test */
    public function it_filters_permits_by_qr_status()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $active = $this->permit(['name' => 'QR ACTIVE USER', 'plate' => 'DT 9101 QA']);
        $expired = $this->permit(['name' => 'QR EXPIRED USER', 'plate' => 'DT 9102 QE']);
        $revoked = $this->permit(['name' => 'QR REVOKED USER', 'plate' => 'DT 9103 QR']);
        $missing = $this->permit(['name' => 'QR MISSING USER', 'plate' => 'DT 9104 QM']);

        $this->token($active, PermitToken::STATUS_ACTIVE, now()->addYear());
        $this->token($expired, PermitToken::STATUS_ACTIVE, now()->subDay());
        $this->token($revoked, PermitToken::STATUS_REVOKED, now()->addYear(), now());

        $reports = app(PermitReportQuery::class);

        $this->assertSame([$active->id], $this->idsForQrStatus($reports, 'active'));
        $this->assertSame([$expired->id], $this->idsForQrStatus($reports, 'expired'));
        $this->assertSame([$revoked->id], $this->idsForQrStatus($reports, 'revoked'));
        $this->assertSame([$missing->id], $this->idsForQrStatus($reports, 'missing'));
    }

    /** @test */
    public function it_resolves_qr_status_labels_from_loaded_tokens()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $permit = $this->permit(['name' => 'QR LABEL USER', 'plate' => 'DT 9201 QL']);
        $this->token($permit, PermitToken::STATUS_ACTIVE, now()->addYear());

        $permit = $permit->fresh(['activeToken', 'latestToken']);
        $reports = app(PermitReportQuery::class);

        $this->assertSame('active', $reports->qrStatusValue($permit));
        $this->assertSame('QR Aktif', $reports->qrStatusLabel($permit));
    }

    /** @test */
    public function it_does_not_hydrate_token_hash_when_loading_report_tokens()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $activePermit = $this->permit(['name' => 'QR HASH ACTIVE', 'plate' => 'DT 9301 QH']);
        $revokedPermit = $this->permit(['name' => 'QR HASH REVOKED', 'plate' => 'DT 9302 QH']);

        $this->token($activePermit, PermitToken::STATUS_ACTIVE, now()->addYear());
        $this->token($revokedPermit, PermitToken::STATUS_REVOKED, now()->addYear(), now());

        $results = app(PermitReportQuery::class)->query([])->get()->keyBy('id');

        $this->assertFalse($results[$activePermit->id]->activeToken->offsetExists('token_hash'));
        $this->assertArrayNotHasKey('token_hash', $results[$activePermit->id]->activeToken->getAttributes());
        $this->assertFalse($results[$revokedPermit->id]->latestToken->offsetExists('token_hash'));
        $this->assertArrayNotHasKey('token_hash', $results[$revokedPermit->id]->latestToken->getAttributes());
    }

    /** @test */
    public function it_resolves_revoked_and_missing_qr_status_labels_from_loaded_tokens()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $revokedPermit = $this->permit(['name' => 'QR LABEL REVOKED', 'plate' => 'DT 9202 QR']);
        $missingPermit = $this->permit(['name' => 'QR LABEL MISSING', 'plate' => 'DT 9203 QM']);

        $this->token($revokedPermit, PermitToken::STATUS_REVOKED, now()->addYear(), now());

        $reports = app(PermitReportQuery::class);
        $results = $reports->query([])->get()->keyBy('id');

        $this->assertSame('revoked', $reports->qrStatusValue($results[$revokedPermit->id]));
        $this->assertSame('QR Dicabut', $reports->qrStatusLabel($results[$revokedPermit->id]));
        $this->assertSame('missing', $reports->qrStatusValue($results[$missingPermit->id]));
        $this->assertSame('Belum dibuat', $reports->qrStatusLabel($results[$missingPermit->id]));
    }

    private function idsForQrStatus(PermitReportQuery $reports, string $qrStatus): array
    {
        return $reports->query($reports->filters(['qr_status' => $qrStatus]))
            ->orderBy('vehicle_permits.id')
            ->get()
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();
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

    private function permit(array $overrides = []): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => $overrides['nik'] ?? 'EMP-' . uniqid(),
            'name' => $overrides['name'] ?? 'REPORT USER',
            'department' => $overrides['department'] ?? 'GA',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $overrides['plate'] ?? 'DT 9000 RP',
            'vehicle_type' => $overrides['vehicle_type'] ?? 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $overrides['parking_location_id'] ?? null,
            'permit_color' => $overrides['permit_color'] ?? 'biru',
            'approval_status' => 'approved',
            'valid_from' => $overrides['valid_from'] ?? now()->toDateString(),
            'valid_until' => $overrides['valid_until'] ?? now()->addYear()->toDateString(),
            'status' => $overrides['status'] ?? VehiclePermit::STATUS_ACTIVE,
            'source' => $overrides['source'] ?? 'import',
            'route_raw' => $overrides['route_raw'] ?? 'Y1',
            'reviewed_by' => $overrides['reviewed_by'] ?? null,
            'reviewed_at' => $overrides['reviewed_at'] ?? null,
            'review_note' => $overrides['review_note'] ?? null,
        ]);
    }

    private function token(VehiclePermit $permit, string $status, ?Carbon $expiresAt, ?Carbon $revokedAt = null): PermitToken
    {
        return PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', uniqid('report-token-', true)),
            'status' => $status,
            'expires_at' => $expiresAt,
            'revoked_at' => $revokedAt,
        ]);
    }
}
