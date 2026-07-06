<?php

namespace App\Services\Routes;

use App\Models\RoadSegment;
use App\Models\VehiclePermit;
use App\Support\RouteMapConfig;

class PermitRouteMapService
{
    private RoadSegmentPolylineService $polylines;

    public function __construct(RoadSegmentPolylineService $polylines)
    {
        $this->polylines = $polylines;
    }

    public function forPermit(VehiclePermit $permit): array
    {
        $permit->loadMissing('routeSegments');

        $allSegments = $permit->routeSegments->values();
        $completeSegments = [];
        $missingSegments = [];

        foreach ($allSegments as $segment) {
            if (! $segment instanceof RoadSegment) {
                continue;
            }

            if ($this->polylines->isComplete($segment->polyline_json)) {
                $segmentDto = $this->polylines->toSegmentDto($segment);
                $completeSegments[] = [
                    'code' => $segment->code,
                    'sequence' => (int) optional($segment->pivot)->sequence,
                    'lat_lngs' => $segmentDto['lat_lngs'],
                ];
            } else {
                $missingSegments[] = $segment->code;
            }
        }

        return [
            'map' => RouteMapConfig::toArray(),
            'route_label' => $allSegments->pluck('code')->filter()->implode(' -> '),
            'segments' => $completeSegments,
            'missing_segments' => $missingSegments,
            'has_route' => $allSegments->isNotEmpty(),
            'is_complete' => $allSegments->isNotEmpty() && count($missingSegments) === 0,
        ];
    }
}
