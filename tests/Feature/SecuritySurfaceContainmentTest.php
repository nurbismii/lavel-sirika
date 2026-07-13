<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class SecuritySurfaceContainmentTest extends TestCase
{
    /** @test */
    public function unused_authenticated_user_api_is_not_exposed()
    {
        $this->getJson('/api/user')->assertNotFound();
    }

    /** @test */
    public function user_model_does_not_expose_unused_sanctum_token_api()
    {
        $this->assertNotContains(HasApiTokens::class, class_uses_recursive(User::class));
    }

    /** @test */
    public function cors_remains_closed_when_no_cross_origin_path_is_configured()
    {
        $this->assertSame([], config('cors.paths'));
        $this->assertFalse(config('cors.supports_credentials'));
    }
}
