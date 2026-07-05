<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
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
    public function authenticated_user_visiting_login_is_redirected_to_dashboard()
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/dashboard');
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
    public function home_route_is_not_exposed_anymore()
    {
        $this->get('/home')->assertNotFound();
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

    /** @test */
    public function super_admin_bypasses_role_middleware()
    {
        Route::middleware(['web', 'auth', 'role:admin_hr'])->get('/role-protected-super-admin-test', function () {
            return 'ok';
        });

        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($superAdmin)
            ->get('/role-protected-super-admin-test')
            ->assertOk()
            ->assertSee('ok');
    }

    /** @test */
    public function dashboard_requires_allowed_active_roles()
    {
        $cases = [
            [
                'role' => User::ROLE_SUPER_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'expected' => 'ok',
            ],
            [
                'role' => User::ROLE_ADMIN_HR,
                'status' => User::STATUS_ACTIVE,
                'expected' => 'ok',
            ],
            [
                'role' => User::ROLE_SECURITY,
                'status' => User::STATUS_ACTIVE,
                'expected' => 'ok',
            ],
            [
                'role' => User::ROLE_AUDITOR,
                'status' => User::STATUS_ACTIVE,
                'expected' => 'ok',
            ],
            [
                'role' => User::ROLE_AUDITOR,
                'status' => User::STATUS_INACTIVE,
                'expected' => 'forbidden',
            ],
            [
                'role' => 'guest_ops',
                'status' => User::STATUS_ACTIVE,
                'expected' => 'forbidden',
            ],
        ];

        foreach ($cases as $index => $case) {
            $user = User::factory()->create([
                'email' => "dashboard-access-{$index}@sirika.local",
                'role' => $case['role'],
                'status' => $case['status'],
            ]);

            $response = $this->actingAs($user)->get('/dashboard');

            $this->assertDashboardExpectation($response, $case['expected'], $case['role'], $case['status']);

            auth()->logout();
        }
    }

    /** @test */
    public function login_attempts_are_rate_limited()
    {
        $server = ['REMOTE_ADDR' => '203.0.113.77'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables($server)->post('/login', [
                'email' => 'missing@sirika.local',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->withServerVariables($server)->post('/login', [
            'email' => 'missing@sirika.local',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    private function assertDashboardExpectation(TestResponse $response, string $expected, string $role, string $status): void
    {
        if ($expected === 'ok') {
            $response->assertOk();

            return;
        }

        $response->assertForbidden();
    }
}
