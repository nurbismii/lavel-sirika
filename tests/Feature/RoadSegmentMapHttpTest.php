<?php

namespace Tests\Feature;

use App\Models\RoadSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoadSegmentMapHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sirika.route_map' => [
                'key' => 'vdni-road-map-v1',
                'image_url' => '/images/maps/vdni-road-map-v1.png',
                'width' => 1600,
                'height' => 1000,
            ],
        ]);
    }

    /** @test */
    public function admin_hr_can_open_road_segment_map_editor()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);
        $segment = $this->segment();

        $response = $this->actingAs($admin)->get(route('road-segments.map', $segment));

        $response->assertOk();
        $response->assertSee('Editor Koordinat');
        $response->assertSee($segment->code);
    }

    /** @test */
    public function auditor_can_open_but_cannot_save_or_reset_road_segment_map()
    {
        $auditor = User::factory()->create([
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
        ]);
        $segment = $this->segment();

        $this->actingAs($auditor)
            ->get(route('road-segments.map', $segment))
            ->assertOk();

        $this->actingAs($auditor)
            ->post(route('road-segments.map.update', $segment), [
                'save_mode' => 'complete',
                'points_json' => json_encode([
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ]),
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->delete(route('road-segments.map.reset', $segment))
            ->assertForbidden();
    }

    /** @test */
    public function security_cannot_open_road_segment_map_editor()
    {
        $security = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($security)
            ->get(route('road-segments.map', $this->segment()))
            ->assertForbidden();
    }

    /** @test */
    public function admin_hr_can_save_complete_polyline()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);
        $segment = $this->segment();

        $response = $this->actingAs($admin)
            ->post(route('road-segments.map.update', $segment), [
                'save_mode' => 'complete',
                'points_json' => json_encode([
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ]),
            ]);

        $response->assertRedirect(route('road-segments.map', $segment));
        $this->assertSame('complete', $segment->fresh()->polyline_json['status']);
        $this->assertSame($admin->id, $segment->fresh()->polyline_json['updated_by']);
    }

    /** @test */
    public function complete_polyline_requires_two_points()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);
        $segment = $this->segment();

        $this->actingAs($admin)
            ->from(route('road-segments.map', $segment))
            ->post(route('road-segments.map.update', $segment), [
                'save_mode' => 'complete',
                'points_json' => json_encode([
                    ['x' => 10, 'y' => 20],
                ]),
            ])
            ->assertRedirect(route('road-segments.map', $segment))
            ->assertSessionHasErrors('points');
    }

    /** @test */
    public function out_of_bounds_points_are_rejected()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);
        $segment = $this->segment();

        $this->actingAs($admin)
            ->from(route('road-segments.map', $segment))
            ->post(route('road-segments.map.update', $segment), [
                'save_mode' => 'complete',
                'points_json' => json_encode([
                    ['x' => 1601, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ]),
            ])
            ->assertRedirect(route('road-segments.map', $segment))
            ->assertSessionHasErrors('points.0.x');
    }

    /** @test */
    public function admin_hr_can_reset_polyline()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);
        $segment = $this->segment([
            'polyline_json' => [
                'status' => 'complete',
                'points' => [
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->delete(route('road-segments.map.reset', $segment))
            ->assertRedirect(route('road-segments.index'));

        $this->assertNull($segment->fresh()->polyline_json);
    }

    private function segment(array $overrides = []): RoadSegment
    {
        return RoadSegment::create(array_merge([
            'code' => 'Y1',
            'name' => 'Y1',
            'start_location' => 'Start',
            'end_location' => 'End',
            'status' => 'active',
        ], $overrides));
    }
}
