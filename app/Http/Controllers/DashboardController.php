<?php

namespace App\Http\Controllers;

use App\Models\RoadSegment;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index', [
            'activeRoadSegments' => RoadSegment::where('status', 'active')->count(),
            'activeUsers' => User::where('status', User::STATUS_ACTIVE)->count(),
            'activePermits' => 0,
            'reviewPermits' => 0,
            'todayScans' => 0,
        ]);
    }
}
