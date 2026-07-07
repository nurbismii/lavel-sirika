<?php

namespace Tests\Feature;

use App\Models\RoadSegment;
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
        $segment = RoadSegment::query()->firstOrFail();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->get('/road-segments')
            ->assertOk()
            ->assertSee('Master Segmen Rute')
            ->assertSee('Y1')
            ->assertSee('H2');

        $this->actingAs($admin)->get(route('road-segments.map', $segment))
            ->assertOk()
            ->assertSee('Editor Koordinat Rute')
            ->assertSee('Simpan Complete')
            ->assertSee('Reset Koordinat');

        $this->actingAs($admin)->get('/imports')
            ->assertOk()
            ->assertSee('Import Excel')
            ->assertSee('Upload Excel')
            ->assertSee('Daftar Batch Import')
            ->assertDontSee('Upload Excel aktif pada fase berikutnya');

        $this->actingAs($admin)->get('/permits')
            ->assertOk()
            ->assertSee('Izin Kendaraan')
            ->assertSee('Daftar izin kendaraan hasil import dan status review.')
            ->assertSee('Daftar Izin')
            ->assertSee('Belum ada data izin kendaraan. Gunakan modul Import Excel untuk membuat data awal.');

        $this->actingAs($admin)->get('/scan')
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('Mulai Kamera')
            ->assertSee('Input Token Manual');
    }

    /** @test */
    public function auditor_can_view_road_segments()
    {
        $this->seed(RoadSegmentSeeder::class);
        $segment = RoadSegment::query()->firstOrFail();

        $auditor = User::factory()->create([
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($auditor)->get('/road-segments')
            ->assertOk()
            ->assertSee('Master Segmen Rute')
            ->assertSee('Y1')
            ->assertSee('H2');

        $this->actingAs($auditor)->get(route('road-segments.map', $segment))
            ->assertOk()
            ->assertSee('Editor Koordinat Rute')
            ->assertDontSee('Simpan Complete')
            ->assertDontSee('Reset Koordinat');
    }

    /** @test */
    public function security_can_access_scan_but_cannot_access_admin_import()
    {
        $this->seed(RoadSegmentSeeder::class);
        $segment = RoadSegment::query()->firstOrFail();

        $security = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($security)->get('/scan')
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('Mulai Kamera')
            ->assertSee('Input Token Manual');

        $this->actingAs($security)->get('/imports')
            ->assertForbidden();

        $this->actingAs($security)->get(route('road-segments.map', $segment))
            ->assertForbidden();
    }
}
