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

    public function test_batch_print_accepts_plate_lists_without_changing_tokens()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $first = $this->permit();
        $second = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 7002 NL');
        $other = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 7003 NL');
        $first->vehicle->update(['plate_number' => 'DT 0123 AB']);
        $second->vehicle->update(['plate_number' => 'dt 7002 xy']);
        $other->update(['employee_id' => $first->employee_id]);
        foreach ([$first, $second, $other] as $permit) {
            app(PermitTokenService::class)->generateForPermit($permit);
        }
        $before = PermitToken::orderBy('id')->get()->toArray();

        $this->actingAs($admin)->post(route('permits.qr.batch-print'), [
            'plate_list' => "dt0123ab\r\nDT 7002 XY,DT  0123 AB; dt7002xy\tDT0123AB",
        ])->assertOk()
            ->assertViewHas('cards', fn ($cards) => $cards->pluck('plate_number')->all() === ['DT 0123 AB', 'dt 7002 xy'])
            ->assertViewHas('missingPlates', [])
            ->assertViewHas('unavailablePlates', []);

        $this->assertSame($before, PermitToken::orderBy('id')->get()->toArray());
    }

    public function test_batch_print_shared_plate_prints_each_employee_qr_once_even_with_duplicate_input()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $first = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 1234 AB');
        $second = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 9999 XY');
        $second->update(['vehicle_id' => $first->vehicle_id]);
        $first->employee->update(['nik' => 'NIK-001', 'name' => 'PEMAKAI PERTAMA']);
        $second->employee->update(['nik' => 'NIK-002', 'name' => 'PEMAKAI KEDUA']);

        foreach ([$first, $second] as $permit) {
            app(PermitTokenService::class)->generateForPermit($permit);
        }
        $before = PermitToken::orderBy('id')->get()->toArray();

        foreach (['DT 1234 AB', "DT 1234 AB\ndt1234ab"] as $input) {
            $response = $this->actingAs($admin)->post(route('permits.qr.batch-print'), [
                'plate_list' => $input,
            ])->assertOk()
                ->assertViewHas('cards', function ($cards) {
                    return $cards->count() === 2
                        && $cards->pluck('nik')->all() === ['NIK-001', 'NIK-002']
                        && $cards->pluck('plate_number')->all() === ['DT 1234 AB', 'DT 1234 AB']
                        && $cards[0]['qrSvg'] !== $cards[1]['qrSvg'];
                })
                ->assertSee('PEMAKAI PERTAMA')
                ->assertSee('PEMAKAI KEDUA')
                ->assertViewHas('missingPlates', [])
                ->assertViewHas('unavailablePlates', []);

            $this->assertSame(2, substr_count($response->getContent(), '<svg'));
        }

        $this->assertSame($before, PermitToken::orderBy('id')->get()->toArray());
    }

    public function test_batch_print_reports_missing_and_unavailable_plates_and_combines_filters()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $plates = [];
        foreach (['READY', 'NO-TOKEN', 'EXPIRED', 'UNREADABLE', 'OTHER-COLOR', 'INACTIVE'] as $index => $plate) {
            $permit = $this->permit(VehiclePermit::STATUS_ACTIVE, 'DT 80' . $index . ' NL');
            $permit->vehicle->update(['plate_number' => $plate]);
            $permit->employee->update(['department' => 'GA', 'division' => 'OPS']);
            $plates[] = $plate;
            if ($plate !== 'NO-TOKEN') {
                $token = app(PermitTokenService::class)->generateForPermit($permit)['permit_token'];
                if ($plate === 'EXPIRED') {
                    $token->update(['expires_at' => now()->subDay()]);
                }
                if ($plate === 'UNREADABLE') {
                    $token->update(['token_encrypted' => null]);
                }
            }
            if ($plate === 'OTHER-COLOR') {
                $permit->update(['permit_color' => 'merah']);
            }
            if ($plate === 'INACTIVE') {
                $permit->update(['status' => VehiclePermit::STATUS_REVOKED]);
            }
        }

        $this->actingAs($admin)->post(route('permits.qr.batch-print'), [
            'plate_list' => implode(',', $plates) . ',MISSING',
            'department' => 'GA', 'division' => 'OPS', 'permit_color' => 'biru',
        ])->assertOk()
            ->assertViewHas('cards', fn ($cards) => $cards->pluck('plate_number')->all() === ['READY'])
            ->assertViewHas('missingPlates', ['MISSING'])
            ->assertViewHas('unavailablePlates', array_slice($plates, 1))
            ->assertSee('Plat tidak ditemukan')
            ->assertSee('Plat tanpa QR siap cetak');
    }

    public function test_batch_print_validates_plate_input_and_preserves_authorization()
    {
        $this->actingAs($this->userWithRole(User::ROLE_ADMIN_HR));
        foreach ([['invalid'], ', ;', str_repeat('X', 101), implode(',', range(1, 501))] as $input) {
            $this->from(route('permits.qr.batch-print'))
                ->post(route('permits.qr.batch-print'), ['plate_list' => $input])
                ->assertRedirect(route('permits.qr.batch-print'))
                ->assertSessionHasErrors('plate_list');
        }
        $this->actingAs($this->userWithRole(User::ROLE_SECURITY))
            ->post(route('permits.qr.batch-print'), ['plate_list' => '00123'])
            ->assertForbidden();
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
