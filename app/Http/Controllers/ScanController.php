<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyScanRequest;
use App\Services\Permits\PermitScanService;

class ScanController extends Controller
{
    public function index()
    {
        return view('scan.index');
    }

    public function verify(VerifyScanRequest $request, PermitScanService $scanner)
    {
        $result = $scanner->scan($request->input('token'), $request->user(), [
            'device_info' => $request->input('device_info'),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'result' => $result['result'],
            'message' => $result['message'],
            'permit' => $result['permit'],
        ]);
    }
}
