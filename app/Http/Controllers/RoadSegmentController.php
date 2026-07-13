<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRoadSegmentPolylineRequest;
use App\Http\Requests\StoreRoadSegmentRequest;
use App\Models\RoadSegment;
use App\Services\Routes\RoadSegmentPolylineService;
use App\Support\RouteMapConfig;

class RoadSegmentController extends Controller
{
    public function create()
    {
        return view('road-segments.create');
    }

    public function store(StoreRoadSegmentRequest $request)
    {
        RoadSegment::create($request->validated() + ['status' => RoadSegment::STATUS_DRAFT]);

        return redirect()->route('road-segments.index')->with('status', 'Segmen rute draft berhasil ditambahkan.');
    }

    public function activate(RoadSegment $roadSegment, RoadSegmentPolylineService $polylines)
    {
        if ($polylines->toSegmentDto($roadSegment)['coordinate_status'] !== RoadSegmentPolylineService::STATUS_COMPLETE) {
            return back()->withErrors(['polyline_json' => 'Segmen hanya dapat diaktifkan setelah polyline lengkap disimpan.']);
        }

        $roadSegment->update(['status' => RoadSegment::STATUS_ACTIVE]);

        return back()->with('status', 'Segmen rute berhasil diaktifkan.');
    }

    public function deactivate(RoadSegment $roadSegment)
    {
        $roadSegment->update(['status' => RoadSegment::STATUS_INACTIVE]);

        return back()->with('status', 'Segmen rute dinonaktifkan.');
    }

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
