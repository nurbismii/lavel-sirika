<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitLifecycleHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_hr_can_revoke_an_active_permit_and_all_of_its_active_qr_tokens()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_ACTIVE);
        $activeToken = $this->token($permit, PermitToken::STATUS_ACTIVE);
        $alreadyRevokedToken = $this->token($permit, PermitToken::STATUS_REVOKED, now()->subDay());

        $this->actingAs($admin)
            ->post(route('permits.deactivate', $permit))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('status', 'Izin kendaraan dan QR aktif berhasil dicabut.');

        $this->assertSame(VehiclePermit::STATUS_REVOKED, $permit->fresh()->status);
        $this->assertSame(PermitToken::STATUS_REVOKED, $activeToken->fresh()->status);
        $this->assertNotNull($activeToken->fresh()->revoked_at);
        $this->assertSame(PermitToken::STATUS_REVOKED, $alreadyRevokedToken->fresh()->status);
    }

    /** @test */
    public function active_permit_cannot_be_permanently_deleted()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_ACTIVE);

        $this->from(route('permits.index'))
            ->actingAs($admin)
            ->delete(route('permits.destroy', $permit))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('error', 'Izin aktif harus dicabut terlebih dahulu sebelum dihapus permanen.');

        $this->assertDatabaseHas('vehicle_permits', ['id' => $permit->id]);
    }

    /** @test */
    public function admin_hr_can_permanently_delete_a_non_active_permit_and_keep_scan_logs_without_permit_reference()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_REVOKED);
        $token = $this->token($permit, PermitToken::STATUS_REVOKED, now()->subDay());
        $scanLog = ScanLog::create([
            'permit_id' => $permit->id,
            'scanned_by' => $admin->id,
            'scanned_at' => now(),
            'result' => ScanLog::RESULT_REVOKED,
        ]);

        $this->actingAs($admin)
            ->delete(route('permits.destroy', $permit))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('status', 'Izin kendaraan berhasil dihapus permanen.');

        $this->assertDatabaseMissing('vehicle_permits', ['id' => $permit->id]);
        $this->assertDatabaseMissing('permit_tokens', ['id' => $token->id]);
        $this->assertDatabaseHas('scan_logs', [
            'id' => $scanLog->id,
            'permit_id' => null,
            'result' => ScanLog::RESULT_REVOKED,
        ]);
    }

    /** @test */
    public function read_only_roles_cannot_manage_permit_lifecycle()
    {
        $permit = $this->permit(VehiclePermit::STATUS_ACTIVE);

        foreach ([User::ROLE_AUDITOR, User::ROLE_SECURITY] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)
                ->post(route('permits.deactivate', $permit))
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('permits.destroy', $permit))
                ->assertForbidden();
        }
    }

    /** @test */
    public function permit_list_only_shows_lifecycle_actions_allowed_for_the_permit_state()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $activePermit = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 7101 LC');
        $revokedPermit = $this->permit(VehiclePermit::STATUS_REVOKED, 'DT 7102 LC');

        $response = $this->actingAs($admin)->get(route('permits.index'));

        $response->assertOk();
        $response->assertSee('Cabut Izin');
        $response->assertSee('Hapus Permanen');
        $response->assertSee('<form method="POST" action="' . route('permits.deactivate', $activePermit) . '"', false);
        $response->assertSee('<form method="POST" action="' . route('permits.destroy', $revokedPermit) . '"', false);
        $response->assertDontSee('<form method="POST" action="' . route('permits.destroy', $activePermit) . '"', false);
        $response->assertDontSee('<form method="POST" action="' . route('permits.deactivate', $revokedPermit) . '"', false);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit(string $status, string $plate = 'DT 7100 LC'): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => 'LC-' . uniqid(),
            'name' => 'Lifecycle User',
        ]);
        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $plate,
            'vehicle_type' => 'Mobil',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'status' => $status,
            'approval_status' => 'approved',
            'source' => 'manual',
        ]);
    }

    private function token(VehiclePermit $permit, string $status, $revokedAt = null): PermitToken
    {
        return PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', uniqid('permit-token-', true)),
            'status' => $status,
            'expires_at' => now()->addYear(),
            'revoked_at' => $revokedAt,
        ]);
    }
}
