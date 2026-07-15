<?php

namespace App\Http\Controllers;

use App\Models\VehiclePermit;
use App\Services\Routes\PermitRouteMapService;

class PermitRouteMapController extends Controller
{
    public function show(VehiclePermit $permit, PermitRouteMapService $routeMaps)
    {
        $permit->loadMissing(['employee', 'vehicle', 'parkingLocations', 'routeSegments']);

        return view('permits.route-map.show', [
            'permit' => $permit,
            'routeMapData' => $routeMaps->forPermit($permit),
        ]);
    }
}
