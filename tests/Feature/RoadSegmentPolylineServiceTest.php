<?php

namespace Tests\Feature;

use App\Models\RoadSegment;
use App\Models\User;
use App\Services\Routes\RoadSegmentPolylineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoadSegmentPolylineServiceTest extends TestCase
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
    public function it_builds_complete_polyline_payload_from_valid_points()
    {
        $user = User::factory()->create();
        $service = app(RoadSegmentPolylineService::class);

        $payload = $service->buildPayload([
            ['x' => '10.1234', 'y' => '20.9876'],
            ['x' => 100, 'y' => 200],
        ], 'complete', $user);

        $this->assertSame(1, $payload['version']);
        $this->assertSame('vdni-road-map-v1', $payload['map_key']);
        $this->assertSame('complete', $payload['status']);
        $this->assertSame($user->id, $payload['updated_by']);
        $this->assertCount(2, $payload['points']);
        $this->assertSame(10.12, $payload['points'][0]['x']);
        $this->assertSame(20.99, $payload['points'][0]['y']);
    }

    /** @test */
    public function it_allows_draft_with_one_valid_point()
    {
        $service = app(RoadSegmentPolylineService::class);

        $payload = $service->buildPayload([
            ['x' => 10, 'y' => 20],
        ], 'draft', null);

        $this->assertSame('draft', $payload['status']);
        $this->assertCount(1, $payload['points']);
    }

    /** @test */
    public function it_rejects_complete_payload_with_less_than_two_points()
    {
        $this->expectException(ValidationException::class);

        app(RoadSegmentPolylineService::class)->buildPayload([
            ['x' => 10, 'y' => 20],
        ], 'complete', null);
    }

    /** @test */
    public function it_rejects_empty_draft_payload()
    {
        $this->expectException(ValidationException::class);

        app(RoadSegmentPolylineService::class)->buildPayload([], 'draft', null);
    }

    /** @test */
    public function it_rejects_points_outside_map_bounds()
    {
        $this->expectException(ValidationException::class);

        app(RoadSegmentPolylineService::class)->buildPayload([
            ['x' => 1601, 'y' => 20],
            ['x' => 100, 'y' => 200],
        ], 'complete', null);
    }

    /** @test */
    public function it_rejects_fractional_points_outside_map_bounds_before_rounding()
    {
        $this->expectException(ValidationException::class);

        app(RoadSegmentPolylineService::class)->buildPayload([
            ['x' => 1600.004, 'y' => 20],
            ['x' => 100, 'y' => 1000.004],
        ], 'complete', null);
    }

    /** @test */
    public function it_converts_stored_points_to_leaflet_lat_lng_pairs()
    {
        $service = app(RoadSegmentPolylineService::class);

        $latLngs = $service->toLeafletLatLngs([
            'points' => [
                ['x' => 10.5, 'y' => 20.25],
                ['x' => 30.75, 'y' => 40.5],
            ],
        ]);

        $this->assertSame([[20.25, 10.5], [40.5, 30.75]], $latLngs);
    }

    /** @test */
    public function it_summarizes_segment_coordinate_statuses()
    {
        $complete = RoadSegment::create([
            'code' => 'Y1',
            'name' => 'Y1',
            'start_location' => 'A',
            'end_location' => 'B',
            'status' => 'active',
            'polyline_json' => [
                'status' => 'complete',
                'points' => [
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ],
            ],
        ]);

        $draft = RoadSegment::create([
            'code' => 'D2',
            'name' => 'D2',
            'start_location' => 'B',
            'end_location' => 'C',
            'status' => 'active',
            'polyline_json' => [
                'status' => 'draft',
                'points' => [
                    ['x' => 50, 'y' => 60],
                ],
            ],
        ]);

        $empty = RoadSegment::create([
            'code' => 'H2',
            'name' => 'H2',
            'start_location' => 'C',
            'end_location' => 'D',
            'status' => 'active',
        ]);

        $summary = app(RoadSegmentPolylineService::class)->summary(collect([$complete, $draft, $empty]));

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['complete']);
        $this->assertSame(1, $summary['draft']);
        $this->assertSame(1, $summary['empty']);
    }
}
