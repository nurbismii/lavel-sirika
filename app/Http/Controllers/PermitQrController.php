<?php

namespace App\Http\Controllers;

use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use InvalidArgumentException;

class PermitQrController extends Controller
{
    private $tokens;

    public function __construct(PermitTokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function generate(VehiclePermit $permit)
    {
        try {
            $result = $this->tokens->generateForPermit($permit);
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWithError($exception->getMessage());
        }

        $permit->load(['employee', 'vehicle', 'parkingLocations', 'activeToken']);

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
        $permit->load(['employee', 'vehicle', 'parkingLocations', 'activeToken']);
        $token = $permit->activeToken;

        abort_unless($token, 404);

        $plainToken = $this->tokens->plainTokenForDisplay($token);

        return view('permits.qr.show', [
            'permit' => $permit,
            'token' => $token,
            'qrSvg' => $plainToken ? $this->tokens->renderSvg($plainToken) : null,
        ]);
    }

    public function print(VehiclePermit $permit)
    {
        try {
            $result = $this->tokens->renewForPermit($permit);
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWithError($exception->getMessage());
        }

        $permit->load(['employee', 'vehicle', 'parkingLocations']);

        return view('permits.qr.print', [
            'permit' => $permit,
            'token' => $result['permit_token'],
            'qrSvg' => $result['qr_svg'],
        ]);
    }

    public function renew(VehiclePermit $permit)
    {
        try {
            $result = $this->tokens->renewForPermit($permit);
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWithError($exception->getMessage());
        }

        $permit->load(['employee', 'vehicle', 'parkingLocations']);

        return view('permits.qr.show', [
            'permit' => $permit,
            'token' => $result['permit_token'],
            'qrSvg' => $result['qr_svg'],
        ]);
    }

    private function redirectWithError(string $message)
    {
        return redirect()
            ->back()
            ->with('error', $message);
    }
}
