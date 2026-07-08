<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitReviewHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function permit_review_routes_are_mapped_to_the_expected_roles()
    {
        $this->assertSame([User::ROLE_ADMIN_HR, User::ROLE_AUDITOR], User::rolesForRoute('permits.index'));
        $this->assertSame([User::ROLE_ADMIN_HR, User::ROLE_AUDITOR], User::rolesForRoute('permits.show'));
        $this->assertSame([User::ROLE_ADMIN_HR], User::rolesForRoute('permits.review.edit'));
        $this->assertSame([User::ROLE_ADMIN_HR], User::rolesForRoute('permits.review.update'));
        $this->assertSame([User::ROLE_ADMIN_HR], User::rolesForRoute('permits.review.activate'));
    }

    /** @test */
    public function admin_can_filter_needs_review_permits_from_list()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $reviewPermit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'REVIEW FILTER USER', 'DT 5101 RF');
        $this->permit(VehiclePermit::STATUS_ACTIVE, 'ACTIVE FILTER USER', 'DT 5102 AF');

        $this->actingAs($admin)
            ->get(route('permits.index', ['status' => VehiclePermit::STATUS_NEEDS_REVIEW]))
            ->assertOk()
            ->assertSee('REVIEW FILTER USER')
            ->assertSee('DT 5101 RF')
            ->assertDontSee('ACTIVE FILTER USER')
            ->assertDontSee('DT 5102 AF')
            ->assertSee(route('permits.review.edit', $reviewPermit), false);
    }

    /** @test */
    public function auditor_can_view_list_and_detail_but_cannot_open_review_form()
    {
        $auditor = $this->user(User::ROLE_AUDITOR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'AUDITOR DETAIL USER', 'DT 5201 AD');

        $this->actingAs($auditor)
            ->get(route('permits.index'))
            ->assertOk()
            ->assertSee('AUDITOR DETAIL USER');

        $this->actingAs($auditor)
            ->get(route('permits.show', $permit))
            ->assertOk()
            ->assertSee('Detail Izin')
            ->assertSee('AUDITOR DETAIL USER')
            ->assertDontSee('Simpan Review')
            ->assertDontSee('Aktifkan Izin');

        $this->actingAs($auditor)
            ->get(route('permits.review.edit', $permit))
            ->assertForbidden();
    }

    /** @test */
    public function security_cannot_view_permit_admin_pages()
    {
        $security = $this->user(User::ROLE_SECURITY);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'SECURITY BLOCKED USER', 'DT 5301 SB');

        $this->actingAs($security)->get(route('permits.index'))->assertForbidden();
        $this->actingAs($security)->get(route('permits.show', $permit))->assertForbidden();
        $this->actingAs($security)->get(route('permits.review.edit', $permit))->assertForbidden();
    }

    /** @test */
    public function admin_is_redirected_when_opening_review_form_for_non_review_permit()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_ACTIVE, 'ACTIVE DIRECT REVIEW USER', 'DT 5351 AR');

        $this->actingAs($admin)
            ->get(route('permits.review.edit', $permit))
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('error', 'Izin ini tidak berada dalam status needs_review.');
    }

    /** @test */
    public function admin_can_save_review_draft()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'DRAFT REVIEW USER', 'DT 5401 DR');
        $parking = $this->parking('P1');

        $this->actingAs($admin)
            ->post(route('permits.review.update', $permit), [
                'parking_location_id' => $parking->id,
                'route_raw' => 'Y1 - D2',
                'review_note' => 'Menunggu aktivasi.',
            ])
            ->assertRedirect(route('permits.review.edit', $permit))
            ->assertSessionHas('status', 'Review izin berhasil disimpan.');

        $permit->refresh();

        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $permit->status);
        $this->assertSame($parking->id, $permit->parking_location_id);
        $this->assertSame('Y1 - D2', $permit->route_raw);
        $this->assertSame('Menunggu aktivasi.', $permit->review_note);
    }

    /** @test */
    public function admin_can_activate_reviewed_permit_and_then_generate_qr_action_is_visible()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'ACTIVATE HTTP USER', 'DT 5501 AH');
        $parking = $this->parking('P1');
        $this->segment('Y1');
        $this->segment('D2');

        $this->actingAs($admin)
            ->post(route('permits.review.activate', $permit), [
                'parking_location_id' => $parking->id,
                'route_raw' => 'Y1 -> D2',
                'review_note' => 'Rute dan parkir sudah valid.',
            ])
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('status', 'Izin berhasil diaktifkan.');

        $permit->refresh();

        $this->assertSame(VehiclePermit::STATUS_ACTIVE, $permit->status);
        $this->assertSame($admin->id, $permit->reviewed_by);
        $this->assertSame(2, $permit->permitRouteSegments()->count());

        $this->actingAs($admin)
            ->get(route('permits.index', ['status' => VehiclePermit::STATUS_ACTIVE]))
            ->assertOk()
            ->assertSee('ACTIVATE HTTP USER')
            ->assertSee('Generate QR')
            ->assertSee(route('permits.qr.generate', $permit), false);
    }

    /** @test */
    public function activation_redirects_back_with_validation_error_when_domain_rule_fails()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'INVALID ROUTE USER', 'DT 5601 IR');
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->from(route('permits.review.edit', $permit))
            ->actingAs($admin)
            ->post(route('permits.review.activate', $permit), [
                'parking_location_id' => $parking->id,
                'route_raw' => 'Y1 -> X99',
                'review_note' => 'Dicoba aktivasi.',
            ])
            ->assertRedirect(route('permits.review.edit', $permit))
            ->assertSessionHasErrors(['activation' => 'Rute mengandung token tidak dikenal: X99']);

        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $permit->fresh()->status);
        $this->assertSame(0, $permit->fresh()->permitRouteSegments()->count());
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function parking(string $code): ParkingLocation
    {
        return ParkingLocation::create([
            'code' => $code,
            'name' => 'Parkir ' . $code,
            'status' => 'active',
        ]);
    }

    private function segment(string $code): RoadSegment
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => 'Jalan ' . $code,
            'start_location' => 'Start ' . $code,
            'end_location' => 'End ' . $code,
            'status' => 'active',
        ]);
    }

    private function permit(string $status, string $name, string $plateNumber): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => $name,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $plateNumber,
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'import',
            'route_raw' => 'Y1',
        ]);
    }
}
