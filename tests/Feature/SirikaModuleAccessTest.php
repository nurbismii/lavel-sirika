<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoadSegmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SirikaModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_road_segments_and_read_only_modules()
    {
        $this->seed(RoadSegmentSeeder::class);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->get('/road-segments')
            ->assertOk()
            ->assertSee('Master Segmen Rute')
            ->assertSee('Y1')
            ->assertSee('H2');

        $this->actingAs($admin)->get('/imports')
            ->assertOk()
            ->assertSee('Import Excel')
            ->assertSee('Upload Excel aktif pada fase berikutnya');

        $this->actingAs($admin)->get('/permits')
            ->assertOk()
            ->assertSee('Izin Kendaraan')
            ->assertSee('Manajemen izin aktif pada fase berikutnya');

        $this->actingAs($admin)->get('/scan')
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('Scanner kamera aktif pada fase berikutnya');
    }

    /** @test */
    public function auditor_can_view_road_segments()
    {
        $this->seed(RoadSegmentSeeder::class);

        $auditor = User::factory()->create([
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($auditor)->get('/road-segments')
            ->assertOk()
            ->assertSee('Master Segmen Rute')
            ->assertSee('Y1')
            ->assertSee('H2');
    }

    /** @test */
    public function security_can_access_scan_but_cannot_access_admin_import()
    {
        $security = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($security)->get('/scan')
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('Scanner kamera aktif pada fase berikutnya');

        $this->actingAs($security)->get('/imports')
            ->assertForbidden();
    }
}
