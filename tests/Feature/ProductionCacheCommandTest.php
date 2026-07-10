<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        parent::tearDown();
    }

    /** @test */
    public function production_cache_commands_complete_successfully()
    {
        $this->assertSame(0, Artisan::call('config:clear'));
        $this->assertSame(0, Artisan::call('route:clear'));
        $this->assertSame(0, Artisan::call('view:clear'));

        $this->assertSame(0, Artisan::call('config:cache'));
        $this->assertSame(0, Artisan::call('route:cache'));
        $this->assertSame(0, Artisan::call('view:cache'));
    }

    /** @test */
    public function root_redirect_behavior_stays_the_same_after_replacing_route_closure()
    {
        $this->get('/')
            ->assertRedirect(route('login'));

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
