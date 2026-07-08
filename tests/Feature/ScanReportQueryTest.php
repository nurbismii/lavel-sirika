<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Reports\ScanReportQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScanReportQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function it_defaults_scan_report_to_last_seven_days()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $filters = app(ScanReportQuery::class)->filters([]);

        $this->assertSame('2026-07-02', $filters['date_from']);
        $this->assertSame('2026-07-08', $filters['date_to']);
    }

    /** @test */
    public function it_filters_scans_by_date_result_scanner_and_search()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $scanner = $this->user(User::ROLE_SECURITY, 'Scanner One');
        $otherScanner = $this->user(User::ROLE_SECURITY, 'Scanner Two');
        $permit = $this->permit('SCAN MATCH USER', 'DT 9601 SM');
        $blockedPermit = $this->permit('SCAN BLOCKED USER', 'DT 9602 SB');

        $matching = $this->scan($permit, $scanner, ScanLog::RESULT_VALID, '2026-07-08 08:00:00');
        $this->scan($blockedPermit, $scanner, ScanLog::RESULT_INVALID, '2026-07-08 09:00:00');
        $this->scan($permit, $otherScanner, ScanLog::RESULT_VALID, '2026-07-08 10:00:00');
        $this->scan($permit, $scanner, ScanLog::RESULT_VALID, '2026-06-30 10:00:00');

        $reports = app(ScanReportQuery::class);
        $results = $reports->query($reports->filters([
            'date_from' => '2026-07-08',
            'date_to' => '2026-07-08',
            'result' => ScanLog::RESULT_VALID,
            'scanner_id' => $scanner->id,
            'search' => '9601',
        ]))->get();

        $this->assertCount(1, $results);
        $this->assertSame($matching->id, $results->first()->id);
    }

    /** @test */
    public function it_rejects_scan_export_ranges_longer_than_thirty_one_days()
    {
        $this->expectException(ValidationException::class);

        app(ScanReportQuery::class)->assertExportRange([
            'date_from' => '2026-07-01',
            'date_to' => '2026-08-01',
        ]);
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

    private function scan(VehiclePermit $permit, User $scanner, string $result, string $scannedAt): ScanLog
    {
        return ScanLog::create([
            'permit_id' => $permit->id,
            'scanned_by' => $scanner->id,
            'scanned_at' => $scannedAt,
            'result' => $result,
            'device_info' => 'Browser Test',
            'ip_address' => '203.0.113.10',
            'notes' => 'QR ' . $result,
        ]);
    }
}
