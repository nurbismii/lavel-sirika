<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoadSegmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function super_admin_sees_all_dashboard_navigation_and_quick_actions()
    {
        $this->seed(RoadSegmentSeeder::class);

        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('class="app-shell"', false)
            ->assertSee('id="sirika-sidebar"', false)
            ->assertSee('aria-controls="sirika-sidebar"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Dashboard SIRIKA')
            ->assertSee('26')
            ->assertSee('Segmen Rute Aktif')
            ->assertSee('Data izin aktif pada tabel final')
            ->assertSee('Izin yang perlu verifikasi lanjutan')
            ->assertSee('Scanner belum aktif')
            ->assertSee('Import Excel dan daftar izin sudah aktif. QR code, scanner kamera, dan peta highlight rute tetap menunggu fase berikutnya.')
            ->assertSee('href="' . route('dashboard') . '"', false);

        $this->assertDashboardLinks($response, [
            route('road-segments.index'),
            route('imports.index'),
            route('permits.index'),
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
                'blocked' => [route('road-segments.index'), route('imports.index'), route('permits.index')],
            ],
            [
                'role' => User::ROLE_ADMIN_HR,
                'allowed' => [route('road-segments.index'), route('imports.index'), route('permits.index'), route('scan.index')],
                'blocked' => [],
            ],
            [
                'role' => User::ROLE_AUDITOR,
                'allowed' => [route('road-segments.index'), route('permits.index')],
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

    private function assertDashboardLinks($response, array $allowed, array $blocked): void
    {
        foreach ($allowed as $href) {
            $response->assertSee('href="' . $href . '"', false);
        }

        foreach ($blocked as $href) {
            $response->assertDontSee('href="' . $href . '"', false);
        }
    }
}
