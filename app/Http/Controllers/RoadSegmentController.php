<?php

namespace App\Http\Controllers;

use App\Models\RoadSegment;

class RoadSegmentController extends Controller
{
    public function index()
    {
        return view('road-segments.index', [
            'segments' => RoadSegment::query()
                ->orderBy('code')
                ->paginate(30),
        ]);
    }
}
