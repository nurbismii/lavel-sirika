<?php

namespace App\Http\Controllers;

use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;

class PermitQrController extends Controller
{
    private $tokens;

    public function __construct(PermitTokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function generate(VehiclePermit $permit)
    {
        $result = $this->tokens->generateForPermit($permit);
        $permit->load(['employee', 'vehicle', 'parkingLocation', 'activeToken']);

        return view('permits.qr.show', [
            'permit' => $permit,
            'token' => $result['permit_token'],
            'qrSvg' => $result['qr_svg'],
        ]);
    }

    public function bulkGenerate()
    {
        $summary = $this->tokens->bulkGenerateForActivePermits();

        return redirect()
            ->route('permits.index')
            ->with('status', "Bulk generate selesai. Dibuat: {$summary['created']}. Dilewati: {$summary['skipped']}.");
    }

    public function show(VehiclePermit $permit)
    {
        $permit->load(['employee', 'vehicle', 'parkingLocation', 'activeToken']);
        $token = $permit->activeToken;

        abort_unless($token, 404);

        return view('permits.qr.show', [
            'permit' => $permit,
            'token' => $token,
            'qrSvg' => null,
        ]);
    }

    public function print(VehiclePermit $permit)
    {
        $result = $this->tokens->renewForPermit($permit);
        $permit->load(['employee', 'vehicle', 'parkingLocation']);

        return view('permits.qr.print', [
            'permit' => $permit,
            'token' => $result['permit_token'],
            'qrSvg' => $result['qr_svg'],
        ]);
    }

    public function renew(VehiclePermit $permit)
    {
        $result = $this->tokens->renewForPermit($permit);
        $permit->load(['employee', 'vehicle', 'parkingLocation']);

        return view('permits.qr.show', [
            'permit' => $permit,
            'token' => $result['permit_token'],
            'qrSvg' => $result['qr_svg'],
        ]);
    }
}
