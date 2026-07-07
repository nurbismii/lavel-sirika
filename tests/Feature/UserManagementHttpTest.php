<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_page_uses_sirika_admin_design_and_accessible_controls()
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Login SIRIKA')
            ->assertSee('Sistem Rute Izin Kendaraan')
            ->assertSee('css/app.css', false)
            ->assertSee('class="login-shell"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    /** @test */
    public function super_admin_can_create_update_view_and_delete_users()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->get('/users')
            ->assertOk()
            ->assertSee('Manajemen User')
            ->assertSee('Tambah User');

        $this->actingAs($admin)
            ->get('/users/create')
            ->assertOk()
            ->assertSee('Tambah User');

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'Operator Security',
                'email' => 'operator.security@sirika.local',
                'role' => User::ROLE_SECURITY,
                'status' => User::STATUS_ACTIVE,
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertRedirect('/users');

        $managedUser = User::where('email', 'operator.security@sirika.local')->firstOrFail();

        $this->assertTrue(Hash::check('secret123', $managedUser->password));

        $this->actingAs($admin)
            ->get('/users/' . $managedUser->id)
            ->assertOk()
            ->assertSee('Detail User')
            ->assertSee('operator.security@sirika.local');

        $existingPassword = $managedUser->password;

        $this->actingAs($admin)
            ->put('/users/' . $managedUser->id, [
                'name' => 'Auditor Operasional',
                'email' => 'auditor.operasional@sirika.local',
                'role' => User::ROLE_AUDITOR,
                'status' => User::STATUS_INACTIVE,
            ])
            ->assertRedirect('/users/' . $managedUser->id);

        $managedUser->refresh();

        $this->assertSame('Auditor Operasional', $managedUser->name);
        $this->assertSame('auditor.operasional@sirika.local', $managedUser->email);
        $this->assertSame(User::ROLE_AUDITOR, $managedUser->role);
        $this->assertSame(User::STATUS_INACTIVE, $managedUser->status);
        $this->assertSame($existingPassword, $managedUser->password);

        $this->actingAs($admin)
            ->delete('/users/' . $managedUser->id)
            ->assertRedirect('/users');

        $this->assertDatabaseMissing('users', [
            'id' => $managedUser->id,
        ]);
    }

    /** @test */
    public function non_super_admin_users_cannot_access_user_management()
    {
        $adminHr = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($adminHr)->get('/users')->assertForbidden();
        $this->actingAs($adminHr)->post('/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@sirika.local',
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertForbidden();
    }

    /** @test */
    public function super_admin_cannot_delete_or_demote_own_account()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->put('/users/' . $admin->id, [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => User::ROLE_ADMIN_HR,
                'status' => User::STATUS_INACTIVE,
            ])
            ->assertRedirect('/users/' . $admin->id)
            ->assertSessionHas('error');

        $admin->refresh();

        $this->assertSame(User::ROLE_SUPER_ADMIN, $admin->role);
        $this->assertSame(User::STATUS_ACTIVE, $admin->status);

        $this->actingAs($admin)
            ->delete('/users/' . $admin->id)
            ->assertRedirect('/users')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }
}
