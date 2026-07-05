<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoadSegmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DashboardUiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dashboard_displays_sirika_operational_foundation_with_pending_modules_disabled()
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
            ->assertSee('Belum ada data izin')
            ->assertSee('Scanner belum aktif')
            ->assertSee('href="' . route('dashboard') . '"', false);

        if (Route::has('imports.index')) {
            $response->assertSee('href="' . route('imports.index') . '"', false);

            return;
        }

        $response->assertSee('aria-disabled="true"', false)
            ->assertSee('Tersedia di Task 7')
            ->assertDontSee('href="' . url('/imports') . '"', false)
            ->assertDontSee('href="' . url('/permits') . '"', false)
            ->assertDontSee('href="' . url('/road-segments') . '"', false)
            ->assertDontSee('href="' . url('/scan') . '"', false);
    }
}
