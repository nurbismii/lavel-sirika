<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Carbon\Carbon;
use Database\Seeders\RoadSegmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function super_admin_sees_operational_dashboard_metrics_and_report_links()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');
        $this->seed(RoadSegmentSeeder::class);

        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
        $scanner = User::factory()->create([
            'name' => 'Security Scanner',
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);
        $activePermit = $this->permit('DASH ACTIVE USER', 'DT 1101 DA', VehiclePermit::STATUS_ACTIVE, [
            'reviewed_by' => $user->id,
            'reviewed_at' => now()->subHour(),
        ]);
        $this->permit('DASH REVIEW USER', 'DT 1102 DR', VehiclePermit::STATUS_NEEDS_REVIEW);
        $this->token($activePermit, now()->addYear());
        $this->token($activePermit, now()->subDay());
        $this->scan($activePermit, $scanner, ScanLog::RESULT_VALID, now()->subMinutes(30));
        $this->scan($activePermit, $scanner, ScanLog::RESULT_INVALID, now()->subMinutes(20));

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('class="app-shell"', false)
            ->assertSee('id="sirika-sidebar"', false)
            ->assertSee('aria-controls="sirika-sidebar"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Dashboard SIRIKA')
            ->assertSeeInOrder([
                'Segmen Rute Aktif',
                '26',
                'Izin Aktif',
                '1',
                'Perlu Review',
                '1',
                'QR Aktif',
                '1',
                'QR Kadaluwarsa',
                '1',
                'Scan Hari Ini',
                '2',
                'Scan Invalid Hari Ini',
                '1',
            ])
            ->assertSee('Ringkasan Status Izin')
            ->assertSee('Hasil Scan 7 Hari')
            ->assertSee('Aktivitas Terbaru')
            ->assertSee('DASH ACTIVE USER')
            ->assertSee('DT 1101 DA')
            ->assertDontSee('Scanner belum aktif')
            ->assertDontSee('Import Excel dan daftar izin sudah aktif. QR code, scanner kamera, dan peta highlight rute tetap menunggu fase berikutnya.')
            ->assertSee('href="' . route('dashboard') . '"', false);

        $this->assertDashboardLinks($response, [
            route('road-segments.index'),
            route('imports.index'),
            route('permits.index'),
            route('reports.permits.index'),
            route('reports.scans.index'),
            route('scan.index'),
        ], []);
    }

    /** @test */
    public function dashboard_links_are_filtered_by_user_role()
    {
        $cases = [
            [
                'role' => User::ROLE_SECURITY,
                'allowed' => [route('scan.index')],
                'blocked' => [
                    route('road-segments.index'),
                    route('imports.index'),
                    route('permits.index'),
                    route('reports.permits.index'),
                    route('reports.scans.index'),
                ],
            ],
            [
                'role' => User::ROLE_ADMIN_HR,
                'allowed' => [
                    route('road-segments.index'),
                    route('imports.index'),
                    route('permits.index'),
                    route('reports.permits.index'),
                    route('reports.scans.index'),
                    route('scan.index'),
                ],
                'blocked' => [],
            ],
            [
                'role' => User::ROLE_AUDITOR,
                'allowed' => [
                    route('road-segments.index'),
                    route('permits.index'),
                    route('reports.permits.index'),
                    route('reports.scans.index'),
                ],
                'blocked' => [route('imports.index'), route('scan.index')],
            ],
        ];

        foreach ($cases as $index => $case) {
            $user = User::factory()->create([
                'email' => "dashboard-role-{$index}@sirika.local",
                'role' => $case['role'],
                'status' => User::STATUS_ACTIVE,
            ]);

            $response = $this->actingAs($user)->get('/dashboard');

            $response->assertOk();
            $this->assertDashboardLinks($response, $case['allowed'], $case['blocked']);

            auth()->logout();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function assertDashboardLinks($response, array $allowed, array $blocked): void
    {
        foreach ($allowed as $href) {
            $response->assertSee('href="' . $href . '"', false);
        }

        foreach ($blocked as $href) {
            $response->assertDontSee('href="' . $href . '"', false);
        }
    }

    private function permit(string $name, string $plate, string $status, array $attributes = []): VehiclePermit
    {
        $parking = ParkingLocation::first() ?: ParkingLocation::create([
            'code' => 'P1',
            'name' => 'Parkir P1',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'nik' => 'DASH-' . uniqid(),
            'name' => $name,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $plate,
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create(array_merge([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'import',
            'route_raw' => 'Y1',
        ], $attributes));
    }

    private function token(VehiclePermit $permit, Carbon $expiresAt): PermitToken
    {
        return PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => 'dashboard-token-' . uniqid(),
            'status' => PermitToken::STATUS_ACTIVE,
            'expires_at' => $expiresAt,
        ]);
    }

    private function scan(VehiclePermit $permit, User $scanner, string $result, Carbon $scannedAt): ScanLog
    {
        return ScanLog::create([
            'permit_id' => $permit->id,
            'scanned_by' => $scanner->id,
            'scanned_at' => $scannedAt,
            'result' => $result,
            'device_info' => 'Dashboard Test',
            'ip_address' => '203.0.113.20',
            'notes' => 'Dashboard scan ' . $result,
        ]);
    }
}
