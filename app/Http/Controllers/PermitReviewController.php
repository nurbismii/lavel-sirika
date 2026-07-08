<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePermitReviewRequest;
use App\Models\ParkingLocation;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitReviewService;
use InvalidArgumentException;

class PermitReviewController extends Controller
{
    private PermitReviewService $reviews;

    public function __construct(PermitReviewService $reviews)
    {
        $this->reviews = $reviews;
    }

    public function edit(VehiclePermit $permit)
    {
        if ($permit->status !== VehiclePermit::STATUS_NEEDS_REVIEW) {
            return redirect()
                ->route('permits.show', $permit)
                ->with('error', 'Izin ini tidak berada dalam status needs_review.');
        }

        $permit->loadMissing(['employee', 'vehicle', 'parkingLocation', 'routeSegments', 'reviewer']);

        return view('permits.review.edit', [
            'permit' => $permit,
            'parkingLocations' => ParkingLocation::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(UpdatePermitReviewRequest $request, VehiclePermit $permit)
    {
        try {
            $this->reviews->saveDraft($permit, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('permits.review.edit', $permit)
                ->withErrors(['review' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('permits.review.edit', $permit)
            ->with('status', 'Review izin berhasil disimpan.');
    }

    public function activate(UpdatePermitReviewRequest $request, VehiclePermit $permit)
    {
        try {
            $this->reviews->activate($permit, $request->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('permits.review.edit', $permit)
                ->withErrors(['activation' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('permits.show', $permit)
            ->with('status', 'Izin berhasil diaktifkan.');
    }
}
