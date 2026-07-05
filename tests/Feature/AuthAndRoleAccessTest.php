<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthAndRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_is_redirected_from_dashboard_to_login()
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    /** @test */
    public function active_user_can_login_and_reaches_dashboard()
    {
        $this->seed(UserSeeder::class);

        $this->post('/login', [
            'email' => 'superadmin@sirika.local',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertNotNull(User::where('email', 'superadmin@sirika.local')->first()->last_login_at);
    }

    /** @test */
    public function inactive_user_cannot_login()
    {
        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@sirika.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_INACTIVE,
        ]);

        $this->post('/login', [
            'email' => 'inactive@sirika.local',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** @test */
    public function authenticated_user_visiting_home_is_redirected_to_dashboard()
    {
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'home-redirect@sirika.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/home')
            ->assertRedirect('/dashboard');
    }

    /** @test */
    public function role_middleware_allows_matching_roles_and_blocks_others()
    {
        Route::middleware(['web', 'auth', 'role:admin_hr,security'])->get('/role-protected-test', function () {
            return 'ok';
        });

        $allowedUser = User::create([
            'name' => 'Admin HR',
            'email' => 'adminhr-test@sirika.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $blockedUser = User::create([
            'name' => 'Auditor',
            'email' => 'auditor-test@sirika.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($allowedUser)
            ->get('/role-protected-test')
            ->assertOk()
            ->assertSee('ok');

        $this->actingAs($blockedUser)
            ->get('/role-protected-test')
            ->assertForbidden();
    }
}
