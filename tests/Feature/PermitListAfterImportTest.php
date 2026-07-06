<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitListAfterImportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_imported_permits()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create([
            'nik' => '200115677',
            'name' => 'FITRIAWATI',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 4423 CI',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $parking = ParkingLocation::create([
            'code' => 'GA-MES1-P01',
            'name' => 'GA-MES1-P01',
            'status' => 'active',
        ]);

        VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
            'route_raw' => 'Y1-D2',
        ]);

        $this->actingAs($admin)->get(route('permits.index'))
            ->assertOk()
            ->assertSee('Izin Kendaraan')
            ->assertSee('FITRIAWATI')
            ->assertSee('DT 4423 CI')
            ->assertSee('GA-MES1-P01')
            ->assertSee('active')
            ->assertSee('Status QR')
            ->assertSee('Belum dibuat')
            ->assertSee('Generate QR')
            ->assertSee('Rute')
            ->assertSee('Y1-D2')
            ->assertSee('Lihat Rute')
            ->assertSee('Bulk Generate QR Aktif');
    }

    /** @test */
    public function admin_sees_qr_active_status_and_actions_when_permit_has_token()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create([
            'nik' => '200115678',
            'name' => 'QR READY USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 8899 QA',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'hijau',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
        ]);

        PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', 'ready-token'),
            'status' => PermitToken::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($admin)->get(route('permits.index'))
            ->assertOk()
            ->assertSee('QR Aktif')
            ->assertSee('Lihat QR')
            ->assertSee('Print')
            ->assertSee('Renew');
    }

    /** @test */
    public function dashboard_uses_real_permit_counts()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create([
            'nik' => '200115677',
            'name' => 'FITRIAWATI',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 4423 CI',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
        ]);

        VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'kuning',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'source' => 'import',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Izin Aktif',
                '1',
                'Data izin aktif pada tabel final',
                'Perlu Review',
                '1',
                'Izin yang perlu verifikasi lanjutan',
            ]);
    }
}
