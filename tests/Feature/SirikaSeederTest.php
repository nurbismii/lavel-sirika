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
    public function user_seeder_creates_required_roles()
    {
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
        $this->seed(UserSeeder::class);

        $originalHash = User::query()
            ->where('email', 'superadmin@sirika.local')
            ->value('password');

        $this->seed(UserSeeder::class);

        $this->assertSame($originalHash, User::query()
            ->where('email', 'superadmin@sirika.local')
            ->value('password'));
    }
}
