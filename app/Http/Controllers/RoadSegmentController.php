<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRoadSegmentPolylineRequest;
use App\Models\RoadSegment;
use App\Services\Routes\RoadSegmentPolylineService;
use App\Support\RouteMapConfig;

class RoadSegmentController extends Controller
{
    public function index(RoadSegmentPolylineService $polylines)
    {
        $allSegments = RoadSegment::query()
            ->orderBy('code')
            ->get();

        return view('road-segments.index', [
            'segments' => RoadSegment::query()
                ->orderBy('code')
                ->paginate(30),
            'summary' => $polylines->summary($allSegments),
            'routeMap' => RouteMapConfig::toArray(),
            'mapSegments' => $allSegments
                ->map(function (RoadSegment $segment) use ($polylines) {
                    return $polylines->toSegmentDto($segment);
                })
                ->filter(function (array $segment) {
                    return $segment['coordinate_status'] === RoadSegmentPolylineService::STATUS_COMPLETE;
                })
                ->values()
                ->all(),
            'canEditMap' => request()->user()->canAccessRoute('road-segments.map.update'),
        ]);
    }

    public function map(RoadSegment $roadSegment, RoadSegmentPolylineService $polylines)
    {
        return view('road-segments.map', [
            'segment' => $roadSegment,
            'routeMap' => RouteMapConfig::toArray(),
            'segmentMap' => $polylines->toSegmentDto($roadSegment),
            'canEditMap' => request()->user()->canAccessRoute('road-segments.map.update'),
        ]);
    }

    public function updateMap(
        UpdateRoadSegmentPolylineRequest $request,
        RoadSegment $roadSegment,
        RoadSegmentPolylineService $polylines
    ) {
        $roadSegment->update([
            'polyline_json' => $polylines->buildPayload(
                $request->input('points', []),
                $request->input('save_mode'),
                $request->user()
            ),
        ]);

        return redirect()
            ->route('road-segments.map', $roadSegment)
            ->with('status', 'Koordinat rute berhasil disimpan.');
    }

    public function resetMap(RoadSegment $roadSegment)
    {
        $roadSegment->update([
            'polyline_json' => null,
        ]);

        return redirect()
            ->route('road-segments.index')
            ->with('status', 'Koordinat rute berhasil direset.');
    }
}
