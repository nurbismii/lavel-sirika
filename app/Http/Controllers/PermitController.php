<?php

namespace App\Http\Controllers;

use App\Models\VehiclePermit;

class PermitController extends Controller
{
    public function index()
    {
        return view('permits.index', [
            'permits' => VehiclePermit::with(['employee', 'vehicle', 'parkingLocation', 'activeToken', 'latestToken', 'routeSegments'])
                ->latest()
                ->paginate(25),
        ]);
    }
}
