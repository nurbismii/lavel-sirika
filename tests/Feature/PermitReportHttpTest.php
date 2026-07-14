<?php

namespace Tests\Feature;

use App\Exports\PermitReportExport;
use App\Exports\PermitNeedsReviewExport;
use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PermitReportHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_and_auditor_can_open_permit_report_but_security_cannot()
    {
        $permit = $this->permit([
            'name' => 'PERMIT REPORT ACCESS',
            'plate' => 'DT 9301 PA',
        ]);

        $this->actingAs($this->user(User::ROLE_ADMIN_HR))
            ->get(route('reports.permits.index'))
            ->assertOk()
            ->assertSee('Laporan Izin')
            ->assertSee('PERMIT REPORT ACCESS')
            ->assertSee('DT 9301 PA');

        $this->actingAs($this->user(User::ROLE_AUDITOR))
            ->get(route('reports.permits.index'))
            ->assertOk()
            ->assertSee('PERMIT REPORT ACCESS');

        $this->actingAs($this->user(User::ROLE_SECURITY))
            ->get(route('reports.permits.index'))
            ->assertForbidden();

        $this->assertNotNull($permit->id);
    }

    /** @test */
    public function permit_report_uses_filters_from_query_string()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $admin = $this->user(User::ROLE_ADMIN_HR);
        $matching = $this->permit([
            'name' => 'FILTERED PERMIT REPORT',
            'plate' => 'DT 9401 FP',
            'status' => VehiclePermit::STATUS_ACTIVE,
        ]);
        $blocked = $this->permit([
            'name' => 'BLOCKED PERMIT REPORT',
            'plate' => 'DT 9402 BP',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
        ]);

        $this->token($matching, PermitToken::STATUS_ACTIVE, now()->addYear());

        $this->actingAs($admin)
            ->get(route('reports.permits.index', [
                'status' => VehiclePermit::STATUS_ACTIVE,
                'qr_status' => 'active',
                'search' => '9401',
            ]))
            ->assertOk()
            ->assertSee('FILTERED PERMIT REPORT')
            ->assertSee('QR Aktif')
            ->assertDontSee('BLOCKED PERMIT REPORT')
            ->assertDontSee('DT 9402 BP');

        $this->assertNotNull($blocked->id);
    }

    /** @test */
    public function permit_report_export_uses_filters_and_does_not_expose_token_hash()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');
        Excel::fake();

        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit([
            'name' => 'EXPORT PERMIT REPORT',
            'plate' => 'DT 9501 EP',
            'status' => VehiclePermit::STATUS_ACTIVE,
        ]);
        $token = $this->token($permit, PermitToken::STATUS_ACTIVE, now()->addYear());

        $this->actingAs($admin)
            ->get(route('reports.permits.export', [
                'status' => VehiclePermit::STATUS_ACTIVE,
                'search' => '9501',
            ]));

        Excel::assertDownloaded('sirika-laporan-izin-20260708-100000.xlsx', function (PermitReportExport $export) use ($permit, $token) {
            $rows = $export->query()->get();
            $this->assertTrue($rows->contains('id', $permit->id));

            $mapped = $export->map($permit->fresh([
                'employee',
                'vehicle',
                'parkingLocation',
                'reviewer',
                'activeToken',
                'latestToken',
            ]));

            $this->assertContains('EXPORT PERMIT REPORT', $mapped);
            $this->assertContains('DT 9501 EP', $mapped);
            $this->assertNotContains($token->token_hash, $mapped);

            return true;
        });
    }

    /** @test */
    public function needs_review_export_only_includes_needs_review_permits_and_flags_unavailable_routes()
    {
        Carbon::setTestNow('2026-07-14 10:00:00');
        Excel::fake();

        $admin = $this->user(User::ROLE_ADMIN_HR);
        RoadSegment::create(['code' => 'Y1', 'name' => 'Y1', 'status' => RoadSegment::STATUS_ACTIVE]);
        RoadSegment::create(['code' => 'D2', 'name' => 'D2', 'status' => RoadSegment::STATUS_INACTIVE]);
        $review = $this->permit(['name' => 'PERLU REVIEW', 'status' => VehiclePermit::STATUS_NEEDS_REVIEW, 'route_raw' => 'Y1 -> D2 -> X99']);
        $active = $this->permit(['name' => 'AKTIF', 'status' => VehiclePermit::STATUS_ACTIVE, 'route_raw' => 'Y1']);
        $token = $this->token($review, PermitToken::STATUS_ACTIVE, now()->addYear());

        $this->actingAs($admin)->get(route('reports.permits.needs-review.export', ['status' => VehiclePermit::STATUS_ACTIVE]));

        Excel::assertDownloaded('sirika-izin-perlu-review-20260714-100000.xlsx', function (PermitNeedsReviewExport $export) use ($review, $active, $token) {
            $rows = $export->query()->get();
            $this->assertTrue($rows->contains('id', $review->id));
            $this->assertFalse($rows->contains('id', $active->id));

            $mapped = $export->map($review->fresh(['employee', 'vehicle', 'parkingLocation', 'reviewer']));
            $this->assertSame('D2, X99', $mapped[8]);
            $this->assertSame('Perlu perbaikan rute', $mapped[9]);
            $this->assertNotContains($token->token_hash, $mapped);
            $this->assertArrayHasKey(\Maatwebsite\Excel\Events\AfterSheet::class, $export->registerEvents());

            return true;
        });

        $this->actingAs($admin)
            ->get(route('reports.permits.index'))
            ->assertOk()
            ->assertSee(route('reports.permits.needs-review.export'))
            ->assertSee('Export Perlu Review');
    }

    /** @test */
    public function needs_review_export_marks_route_as_available_when_all_route_tokens_are_active()
    {
        Carbon::setTestNow('2026-07-14 10:00:00');

        RoadSegment::create(['code' => 'Y1', 'name' => 'Y1', 'status' => RoadSegment::STATUS_ACTIVE]);
        $permit = $this->permit([
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'route_raw' => 'Y1',
        ]);

        $export = new PermitNeedsReviewExport(app(\App\Services\Reports\PermitReportQuery::class), [
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
        ]);

        $mapped = $export->map($permit->fresh(['employee', 'vehicle', 'parkingLocation', 'reviewer']));

        $this->assertContains('Rute tersedia', $mapped);
        $this->assertContains('-', $mapped);
        $this->assertNotContains('Perlu perbaikan rute', $mapped);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit(array $overrides = []): VehiclePermit
    {
        $parking = ParkingLocation::first() ?: ParkingLocation::create([
            'code' => 'P1',
            'name' => 'Parkir P1',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'nik' => $overrides['nik'] ?? 'EMP-' . uniqid(),
            'name' => $overrides['name'] ?? 'REPORT PERMIT USER',
            'department' => $overrides['department'] ?? 'GA',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $overrides['plate'] ?? 'DT 9300 RP',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => $overrides['permit_color'] ?? 'biru',
            'approval_status' => 'approved',
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'status' => $overrides['status'] ?? VehiclePermit::STATUS_ACTIVE,
            'source' => $overrides['source'] ?? 'import',
            'route_raw' => $overrides['route_raw'] ?? 'Y1',
        ]);
    }

    private function token(VehiclePermit $permit, string $status, $expiresAt): PermitToken
    {
        return PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', uniqid('permit-report-token-', true)),
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }
}
