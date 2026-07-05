<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRoleFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function users_table_has_sirika_role_columns()
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'role',
            'status',
            'last_login_at',
        ]));
    }

    /** @test */
    public function user_role_helpers_are_available()
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->assertTrue($user->hasRole(User::ROLE_SECURITY));
        $this->assertFalse($user->hasRole(User::ROLE_ADMIN_HR));
        $this->assertTrue($user->hasAnyRole([User::ROLE_AUDITOR, User::ROLE_SECURITY]));
        $this->assertTrue($user->isActive());
    }
}
