<?php

namespace Tests\Feature;

use App\Models\RoadSegment;
use App\Models\User;
use Database\Seeders\RoadSegmentSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }
}
