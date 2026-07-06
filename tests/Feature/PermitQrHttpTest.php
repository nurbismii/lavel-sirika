<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitQrHttpTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole($role)
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit($status = VehiclePermit::STATUS_ACTIVE)
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'QR HTTP USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 7001 QR',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $parking = ParkingLocation::create([
            'code' => 'GA-MES1-P01-' . uniqid(),
            'name' => 'GA-MES1-P01',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'manual',
        ]);
    }

    /** @test */
    public function admin_can_generate_show_print_and_renew_qr_for_active_permit()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();

        $this->actingAs($admin)->post(route('permits.qr.generate', $permit))
            ->assertOk()
            ->assertSee('QR Digital')
            ->assertSee('<svg', false)
            ->assertSee('QR HTTP USER')
            ->assertSee('DT 7001 QR');

        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());

        $this->actingAs($admin)->get(route('permits.qr.show', $permit))
            ->assertOk()
            ->assertSee('QR Digital')
            ->assertSee('QR HTTP USER')
            ->assertSee('DT 7001 QR')
            ->assertSee('QR lama tidak bisa ditampilkan ulang karena token mentah tidak disimpan. Gunakan renew untuk membuat QR baru.')
            ->assertDontSee('<svg', false);

        $generatedTokenId = $permit->fresh()->activeToken->id;

        $this->actingAs($admin)->post(route('permits.qr.print', $permit))
            ->assertOk()
            ->assertSee('SIRIKA VDNI')
            ->assertSee('DT 7001 QR')
            ->assertSee('<svg', false);

        $printedTokenId = $permit->fresh()->activeToken->id;

        $this->assertSame(PermitToken::STATUS_REVOKED, PermitToken::find($generatedTokenId)->status);
        $this->assertNotSame($generatedTokenId, $printedTokenId);

        $this->actingAs($admin)->post(route('permits.qr.renew', $permit))
            ->assertOk()
            ->assertSee('QR Digital')
            ->assertSee('<svg', false);

        $this->assertSame(PermitToken::STATUS_REVOKED, PermitToken::find($printedTokenId)->status);
        $this->assertNotSame($printedTokenId, $permit->fresh()->activeToken->id);
    }

    /** @test */
    public function security_cannot_access_admin_qr_routes()
    {
        $security = $this->userWithRole(User::ROLE_SECURITY);
        $permit = $this->permit();

        $this->actingAs($security)->post(route('permits.qr.generate', $permit))->assertForbidden();
        $this->actingAs($security)->get(route('permits.qr.show', $permit))->assertForbidden();
        $this->actingAs($security)->post(route('permits.qr.print', $permit))->assertForbidden();
        $this->actingAs($security)->post(route('permits.qr.renew', $permit))->assertForbidden();
    }

    /** @test */
    public function bulk_generate_creates_tokens_for_active_permits_without_existing_active_token()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $first = $this->permit();
        $second = $this->permit();
        $review = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);

        app(PermitTokenService::class)->generateForPermit($second);

        $this->actingAs($admin)->post(route('permits.qr.bulk-generate'))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('status');

        $this->assertNotNull($first->fresh()->activeToken);
        $this->assertNotNull($second->fresh()->activeToken);
        $this->assertNull($review->fresh()->activeToken);
    }

    /** @test */
    public function generate_redirects_with_flash_error_when_active_qr_already_exists()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();

        $this->actingAs($admin)->post(route('permits.qr.generate', $permit))->assertOk();

        $this->from(route('permits.index'))
            ->actingAs($admin)
            ->post(route('permits.qr.generate', $permit))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('error', 'QR aktif sudah tersedia. Gunakan renew untuk membuat QR baru.');

        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());
        $this->assertSame(PermitToken::STATUS_ACTIVE, $permit->fresh()->activeToken->status);
    }

    /** @test */
    public function qr_admin_flash_errors_are_visible_after_redirect()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();

        $this->actingAs($admin)->post(route('permits.qr.generate', $permit))->assertOk();

        $this->followingRedirects()
            ->from(route('permits.index'))
            ->actingAs($admin)
            ->post(route('permits.qr.generate', $permit))
            ->assertOk()
            ->assertSee('QR aktif sudah tersedia. Gunakan renew untuk membuat QR baru.');
    }

    /** @test */
    public function renew_and_print_redirect_with_flash_error_for_non_active_permit()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);

        $this->from(route('permits.index'))
            ->actingAs($admin)
            ->post(route('permits.qr.renew', $permit))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('error', 'QR hanya dapat dibuat untuk izin aktif.');

        $this->from(route('permits.index'))
            ->actingAs($admin)
            ->post(route('permits.qr.print', $permit))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('error', 'QR hanya dapat dibuat untuk izin aktif.');

        $this->assertSame(0, PermitToken::where('vehicle_permit_id', $permit->id)->count());
    }
}
