<?php

namespace App\Http\Controllers;

use App\Models\RoadSegment;
use App\Models\ScanLog;
use App\Models\VehiclePermit;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index', [
            'pageTitle' => 'Dashboard SIRIKA',
            'pageDescription' => 'Ringkasan operasional fondasi sistem rute izin kendaraan.',
            'activeRoadSegments' => RoadSegment::where('status', 'active')->count(),
            'activeUsers' => User::where('status', User::STATUS_ACTIVE)->count(),
            'activePermits' => VehiclePermit::where('status', VehiclePermit::STATUS_ACTIVE)->count(),
            'reviewPermits' => VehiclePermit::where('status', VehiclePermit::STATUS_NEEDS_REVIEW)->count(),
            'todayScans' => ScanLog::whereDate('scanned_at', today())->count(),
        ]);
    }
}
