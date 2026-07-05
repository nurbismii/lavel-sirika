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
    public function dashboard_displays_sirika_operational_foundation()
    {
        $this->seed(RoadSegmentSeeder::class);

        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard SIRIKA')
            ->assertSee('26')
            ->assertSee('Segmen Rute Aktif')
            ->assertSee('Belum ada data izin')
            ->assertSee('Scanner belum aktif');
    }
}
