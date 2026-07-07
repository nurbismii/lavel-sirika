<?php

namespace Tests\Feature;

use App\Models\RoadSegment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoadSegmentSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SirikaSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config()->offsetUnset('sirika.seed_user_password');

        parent::tearDown();
    }

    /** @test */
    public function road_segment_seeder_creates_26_active_segments()
    {
        $this->seed(RoadSegmentSeeder::class);

        $this->assertSame(26, RoadSegment::count());
        $this->assertDatabaseHas('road_segments', [
            'code' => 'Y1',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('road_segments', [
            'code' => 'H2',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function road_segment_seeder_adds_starter_map_coordinates_for_curated_segments()
    {
        $this->seed(RoadSegmentSeeder::class);

        foreach (['Y1', 'D2', 'Z1', 'D3'] as $code) {
            $polyline = RoadSegment::where('code', $code)->value('polyline_json');

            $this->assertIsArray($polyline, $code . ' should have starter coordinates.');
            $this->assertSame('complete', $polyline['status']);
            $this->assertGreaterThanOrEqual(2, count($polyline['points']));
        }
    }

    /** @test */
    public function road_segment_seeder_preserves_existing_map_coordinates()
    {
        $this->seed(RoadSegmentSeeder::class);

        RoadSegment::where('code', 'Y1')->update([
            'polyline_json' => [
                'version' => 1,
                'map_key' => 'custom-map',
                'status' => 'complete',
                'points' => [
                    ['x' => 1, 'y' => 2],
                    ['x' => 3, 'y' => 4],
                ],
                'updated_by' => 99,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        $this->seed(RoadSegmentSeeder::class);

        $polyline = RoadSegment::where('code', 'Y1')->value('polyline_json');

        $this->assertSame('custom-map', $polyline['map_key']);
        $this->assertSame([['x' => 1, 'y' => 2], ['x' => 3, 'y' => 4]], $polyline['points']);
    }

    /** @test */
    public function user_seeder_uses_configured_password_for_starter_accounts()
    {
        config(['sirika.seed_user_password' => 'starter-secret']);

        $this->seed(UserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'superadmin@sirika.local',
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'adminhr@sirika.local',
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'security@sirika.local',
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'auditor@sirika.local',
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $users = User::query()->get(['email', 'password']);

        foreach ($users as $user) {
            $this->assertTrue(Hash::check('starter-secret', $user->password), "Password hash mismatch for {$user->email}");
        }
    }

    /** @test */
    public function user_seeder_falls_back_to_default_password_for_local_and_testing()
    {
        $this->seed(UserSeeder::class);

        $users = User::query()->get(['email', 'password']);

        foreach ($users as $user) {
            $this->assertTrue(Hash::check('password', $user->password), "Password hash mismatch for {$user->email}");
        }
    }

    /** @test */
    public function database_seeder_registers_starter_users_and_road_segments()
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, User::count());
        $this->assertSame(26, RoadSegment::count());
    }

    /** @test */
    public function user_seeder_preserves_existing_password_hash_on_rerun()
    {
        config(['sirika.seed_user_password' => 'starter-secret']);

        $this->seed(UserSeeder::class);

        $originalHash = User::query()
            ->where('email', 'superadmin@sirika.local')
            ->value('password');

        $this->seed(UserSeeder::class);

        $this->assertSame($originalHash, User::query()
            ->where('email', 'superadmin@sirika.local')
            ->value('password'));
    }

    /** @test */
    public function user_seeder_aborts_in_production_when_seed_password_is_missing()
    {
        $this->app->detectEnvironment(function () {
            return 'production';
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SIRIKA_SEED_USER_PASSWORD');

        (new UserSeeder())->run();
    }
}
