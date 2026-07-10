<?php

namespace Tests\Feature;

use App\Exports\ScanReportExport;
use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ScanReportHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function admin_and_auditor_can_open_scan_report_but_security_cannot()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $scanner = $this->user(User::ROLE_SECURITY, 'Security Scanner');
        $permit = $this->permit('SCAN REPORT ACCESS', 'DT 9701 SA');
        $this->scan($permit, $scanner, ScanLog::RESULT_VALID, '2026-07-08 08:00:00');

        $this->actingAs($this->user(User::ROLE_ADMIN_HR, 'Admin HR'))
            ->get(route('reports.scans.index'))
            ->assertOk()
            ->assertSee('Laporan Scan')
            ->assertSee('SCAN REPORT ACCESS')
            ->assertSee('DT 9701 SA')
            ->assertSee('Valid')
            ->assertSee(route('reports.scans.export', [
                'date_from' => '2026-07-02',
                'date_to' => '2026-07-08',
            ]));

        $this->actingAs($this->user(User::ROLE_AUDITOR, 'Auditor'))
            ->get(route('reports.scans.index'))
            ->assertOk()
            ->assertSee('SCAN REPORT ACCESS');

        $this->actingAs($this->user(User::ROLE_SECURITY, 'Blocked Security'))
            ->get(route('reports.scans.index'))
            ->assertForbidden();
    }

    /** @test */
    public function scan_report_uses_filters_from_query_string()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $admin = $this->user(User::ROLE_ADMIN_HR, 'Admin HR');
        $scanner = $this->user(User::ROLE_SECURITY, 'Scanner Filter');
        $matchingPermit = $this->permit('FILTERED SCAN REPORT', 'DT 9801 FS');
        $blockedPermit = $this->permit('BLOCKED SCAN REPORT', 'DT 9802 BS');

        $this->scan($matchingPermit, $scanner, ScanLog::RESULT_VALID, '2026-07-08 08:00:00');
        $this->scan($blockedPermit, $scanner, ScanLog::RESULT_INVALID, '2026-07-08 08:30:00');

        $this->actingAs($admin)
            ->get(route('reports.scans.index', [
                'date_from' => '2026-07-08',
                'date_to' => '2026-07-08',
                'result' => ScanLog::RESULT_VALID,
                'scanner_id' => $scanner->id,
                'search' => '9801',
            ]))
            ->assertOk()
            ->assertSee('FILTERED SCAN REPORT')
            ->assertSee('DT 9801 FS')
            ->assertDontSee('BLOCKED SCAN REPORT')
            ->assertDontSee('DT 9802 BS');
    }

    /** @test */
    public function scan_report_export_rejects_ranges_longer_than_thirty_one_days()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $admin = $this->user(User::ROLE_ADMIN_HR, 'Admin HR');

        $this->from(route('reports.scans.index'))
            ->actingAs($admin)
            ->get(route('reports.scans.export', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-08-01',
            ]))
            ->assertRedirect(route('reports.scans.index'))
            ->assertSessionHasErrors(['date_range' => 'Rentang laporan scan maksimal 31 hari.']);
    }

    /** @test */
    public function scan_report_export_requires_date_range()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $admin = $this->user(User::ROLE_ADMIN_HR, 'Admin HR');

        $this->from(route('reports.scans.index'))
            ->actingAs($admin)
            ->get(route('reports.scans.export'))
            ->assertRedirect(route('reports.scans.index'))
            ->assertSessionHasErrors([
                'date_from' => 'Tanggal awal wajib diisi untuk export laporan scan.',
                'date_to' => 'Tanggal akhir wajib diisi untuk export laporan scan.',
            ]);
    }

    /** @test */
    public function scan_report_export_uses_filters_and_does_not_expose_ip_address()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');
        Excel::fake();

        $admin = $this->user(User::ROLE_ADMIN_HR, 'Admin HR');
        $scanner = $this->user(User::ROLE_SECURITY, 'Export Scanner');
        $permit = $this->permit('EXPORT SCAN REPORT', 'DT 9901 ES');
        $scan = $this->scan($permit, $scanner, ScanLog::RESULT_VALID, '2026-07-08 08:00:00', '203.0.113.44');

        $this->actingAs($admin)
            ->get(route('reports.scans.export', [
                'date_from' => '2026-07-08',
                'date_to' => '2026-07-08',
                'result' => ScanLog::RESULT_VALID,
            ]));

        Excel::assertDownloaded('sirika-laporan-scan-20260708-100000.xlsx', function (ScanReportExport $export) use ($scan) {
            $rows = $export->query()->get();
            $this->assertTrue($rows->contains('id', $scan->id));

            $mapped = $export->map($scan->fresh([
                'permit.employee',
                'permit.vehicle',
                'permit.parkingLocation',
                'scanner',
            ]));

            $this->assertContains('EXPORT SCAN REPORT', $mapped);
            $this->assertContains('DT 9901 ES', $mapped);
            $this->assertNotContains('203.0.113.44', $mapped);

            return true;
        });
    }

    private function user(string $role, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit(string $name, string $plate): VehiclePermit
    {
        $parking = ParkingLocation::first() ?: ParkingLocation::create([
            'code' => 'P1',
            'name' => 'Parkir P1',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => $name,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $plate,
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
            'route_raw' => 'Y1',
        ]);
    }

    private function scan(VehiclePermit $permit, User $scanner, string $result, string $scannedAt, string $ip = '203.0.113.10'): ScanLog
    {
        return ScanLog::create([
            'permit_id' => $permit->id,
            'scanned_by' => $scanner->id,
            'scanned_at' => $scannedAt,
            'result' => $result,
            'device_info' => 'Browser Test',
            'ip_address' => $ip,
            'notes' => 'QR ' . $result,
        ]);
    }
}
