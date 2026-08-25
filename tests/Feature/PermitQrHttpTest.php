<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use App\Services\Permits\PermitScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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

    private function permit($status = VehiclePermit::STATUS_ACTIVE, $plateNumber = 'DT 7001 QR')
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'QR HTTP USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $plateNumber,
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

    public function test_admin_can_generate_show_print_without_changing_code_and_renew_qr_for_active_permit()
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
            ->assertSee('<svg', false);

        $generatedTokenId = $permit->fresh()->activeToken->id;

        $this->actingAs($admin)->post(route('permits.qr.print', $permit))
            ->assertOk()
            ->assertSee('SIRIKA VDNI')
            ->assertSee('DT 7001 QR')
            ->assertSee('<svg', false)
            ->assertSee('class="permit-card__qr"', false);

        $printedTokenId = $permit->fresh()->activeToken->id;

        $this->assertSame(PermitToken::STATUS_ACTIVE, PermitToken::find($generatedTokenId)->status);
        $this->assertSame($generatedTokenId, $printedTokenId);
        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());

        $this->actingAs($admin)->post(route('permits.qr.renew', $permit))
            ->assertOk()
            ->assertSee('QR Digital')
            ->assertSee('<svg', false);

        $this->assertSame(PermitToken::STATUS_REVOKED, PermitToken::find($printedTokenId)->status);
        $this->assertNotSame($printedTokenId, $permit->fresh()->activeToken->id);
    }

    public function test_extending_validity_and_printing_keeps_the_existing_qr_code()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();
        $result = app(PermitTokenService::class)->generateForPermit($permit);
        $token = $result['permit_token'];
        $extendedUntil = now()->addYears(2);

        $permit->update(['valid_until' => $extendedUntil->toDateString()]);
        $token->update(['expires_at' => $extendedUntil]);

        $this->actingAs($admin)->post(route('permits.qr.print', $permit))
            ->assertOk()
            ->assertSee('<svg', false);

        $token->refresh();

        $this->assertSame($token->id, $permit->fresh()->activeToken->id);
        $this->assertSame(hash('sha256', $result['plain_token']), $token->token_hash);
        $this->assertTrue($token->expires_at->isSameDay($extendedUntil));
        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());
    }

    public function test_admin_can_extend_expired_qr_validity_without_changing_the_code()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();
        $result = app(PermitTokenService::class)->generateForPermit($permit);
        $token = $result['permit_token'];
        $originalHash = $token->token_hash;
        $originalEncryptedToken = $token->token_encrypted;
        $newExpiry = now()->addYears(2)->toDateString();

        $permit->update(['valid_until' => now()->subDay()->toDateString()]);
        $token->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($admin)
            ->post(route('permits.qr.extend', $permit), ['valid_until' => $newExpiry])
            ->assertRedirect(route('permits.qr.show', $permit))
            ->assertSessionHas('status', 'Masa berlaku QR berhasil diperpanjang tanpa mengubah kode QR.');

        $permit->refresh();
        $token->refresh();

        $this->assertSame($newExpiry, $permit->valid_until->toDateString());
        $this->assertSame($newExpiry, $token->expires_at->toDateString());
        $this->assertSame($token->id, $permit->activeToken->id);
        $this->assertSame($originalHash, $token->token_hash);
        $this->assertSame($originalEncryptedToken, $token->token_encrypted);
        $this->assertSame($result['plain_token'], Crypt::decryptString($token->token_encrypted));
        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());

        $scanResult = app(PermitScanService::class)->scan(
            $result['plain_token'],
            $this->userWithRole(User::ROLE_SECURITY)
        );

        $this->assertSame('valid', $scanResult['result']);
    }

    public function test_qr_validity_extension_rejects_a_date_that_does_not_extend_current_validity()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();
        $result = app(PermitTokenService::class)->generateForPermit($permit);
        $token = $result['permit_token'];
        $originalExpiry = $token->expires_at->copy();

        $this->from(route('permits.qr.show', $permit))
            ->actingAs($admin)
            ->post(route('permits.qr.extend', $permit), [
                'valid_until' => $originalExpiry->toDateString(),
            ])
            ->assertRedirect(route('permits.qr.show', $permit))
            ->assertSessionHas('error');

        $this->assertSame($originalExpiry->toDateTimeString(), $token->fresh()->expires_at->toDateTimeString());
        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());
    }

    public function test_admin_can_display_an_active_qr_without_renewing_it()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();

        $this->actingAs($admin)->post(route('permits.qr.generate', $permit));
        $activeTokenId = $permit->fresh()->activeToken->id;

        $this->actingAs($admin)->get(route('permits.qr.show', $permit))
            ->assertOk()
            ->assertSee('QR Digital')
            ->assertSee('<svg', false);

        $this->assertSame($activeTokenId, $permit->fresh()->activeToken->id);
    }

    public function test_qr_views_render_all_selected_parking_locations()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();
        $secondParking = ParkingLocation::create([
            'code' => 'AA-MES1-P01-' . uniqid(),
            'name' => 'AA-MES1-P01',
            'status' => 'active',
        ]);
        $permit->parkingLocations()->sync([$permit->parking_location_id, $secondParking->id]);

        $this->actingAs($admin)->post(route('permits.qr.generate', $permit))
            ->assertOk()
            ->assertSee($secondParking->code . ', ' . $permit->parkingLocation->code);
    }

    public function test_security_cannot_access_admin_qr_routes()
    {
        $security = $this->userWithRole(User::ROLE_SECURITY);
        $permit = $this->permit();

        $this->actingAs($security)->post(route('permits.qr.generate', $permit))->assertForbidden();
        $this->actingAs($security)->get(route('permits.qr.show', $permit))->assertForbidden();
        $this->actingAs($security)->post(route('permits.qr.print', $permit))->assertForbidden();
        $this->actingAs($security)->post(route('permits.qr.renew', $permit))->assertForbidden();
        $this->actingAs($security)->post(route('permits.qr.extend', $permit), [
            'valid_until' => now()->addYears(2)->toDateString(),
        ])->assertForbidden();
        $this->actingAs($security)->get(route('permits.qr.batch-print'))->assertForbidden();
    }

    public function test_bulk_generate_creates_tokens_for_active_permits_without_existing_active_token()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $first = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 7001 Q1');
        $second = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 7002 Q2');
        $review = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'DT 7003 Q3');

        app(PermitTokenService::class)->generateForPermit($second);

        $this->actingAs($admin)->post(route('permits.qr.bulk-generate'))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('status');

        $this->assertNotNull($first->fresh()->activeToken);
        $this->assertNotNull($second->fresh()->activeToken);
        $this->assertNull($review->fresh()->activeToken);
    }

    public function test_admin_can_batch_print_only_currently_active_qr_codes_with_employee_identity()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $active = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 7001 BP');
        $inactive = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'DT 7002 BP');

        app(PermitTokenService::class)->generateForPermit($active);

        $this->actingAs($admin)->get(route('permits.qr.batch-print'))
            ->assertOk()
            ->assertSee('Cetak Batch QR Aktif')
            ->assertSee('QR HTTP USER')
            ->assertSee($active->employee->nik)
            ->assertSee('<svg', false)
            ->assertDontSee('DT 7002 BP');

        $this->assertNull($inactive->fresh()->activeToken);
    }

    public function test_admin_can_filter_batch_print_qr_codes_by_department_division_and_card_color()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $matching = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 7001 BF');
        $other = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 7002 BF');

        $matching->employee->update([
            'name' => 'FILTER MATCH',
            'department' => 'GA',
            'division' => 'OPERASIONAL',
        ]);
        $other->employee->update([
            'name' => 'FILTER OTHER',
            'department' => 'HR',
            'division' => 'PEOPLE',
        ]);
        $other->update(['permit_color' => 'merah']);

        app(PermitTokenService::class)->generateForPermit($matching);
        app(PermitTokenService::class)->generateForPermit($other);

        $response = $this->actingAs($admin)->get(route('permits.qr.batch-print', [
            'department' => 'GA',
            'division' => 'OPERASIONAL',
            'permit_color' => 'biru',
        ]));

        $response->assertOk()
            ->assertSee('FILTER MATCH')
            ->assertDontSee('FILTER OTHER')
            ->assertSee('GA')
            ->assertSee('OPERASIONAL')
            ->assertSee('biru');

        $this->assertSame(1, substr_count($response->getContent(), '<svg'));
    }

    public function test_generate_redirects_with_flash_error_when_active_qr_already_exists()
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

    public function test_qr_admin_flash_errors_are_visible_after_redirect()
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

    public function test_renew_and_print_redirect_with_flash_error_for_non_active_permit()
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
