<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePermitDataRequest;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\RoadSegment;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitDataEditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermitController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'status' => $request->query('status'),
            'qr_status' => $request->query('qr_status'),
            'permit_color' => $request->query('permit_color'),
            'parking_location_id' => $request->query('parking_location_id'),
            'search' => $request->query('search'),
        ];

        $query = VehiclePermit::query()
            ->with(['employee', 'vehicle', 'parkingLocations', 'activeToken', 'latestToken', 'routeSegments'])
            ->latest();

        $this->applyFilters($query, $filters);

        return view('permits.index', [
            'permits' => $query->paginate(25)->appends($request->query()),
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'qrStatusOptions' => $this->qrStatusOptions(),
            'colorOptions' => $this->colorOptions(),
            'parkingLocations' => ParkingLocation::query()->orderBy('code')->get(),
            'statusSummary' => $this->statusSummary(),
        ]);
    }

    public function show(VehiclePermit $permit)
    {
        $permit->loadMissing([
            'employee',
            'vehicle',
            'parkingLocations',
            'activeToken',
            'latestToken',
            'routeSegments',
            'reviewer',
        ]);

        return view('permits.show', [
            'permit' => $permit,
        ]);
    }

    public function edit(VehiclePermit $permit)
    {
        $permit->loadMissing([
            'employee',
            'vehicle',
            'parkingLocations',
            'routeSegments',
        ]);

        return view('permits.edit', [
            'permit' => $permit,
            'parkingLocations' => ParkingLocation::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(),
            'roadSegments' => RoadSegment::query()
                ->where('status', RoadSegment::STATUS_ACTIVE)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(
        UpdatePermitDataRequest $request,
        VehiclePermit $permit,
        PermitDataEditService $permitDataEditService
    ) {
        $permitDataEditService->update($permit, $request->validated());

        return redirect()
            ->route('permits.show', $permit)
            ->with('success', 'Data izin kendaraan berhasil diperbarui.');
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['status'] && array_key_exists($filters['status'], $this->statusOptions())) {
            $query->where('status', $filters['status']);
        }

        if ($filters['permit_color']) {
            $query->where('permit_color', $filters['permit_color']);
        }

        if ($filters['parking_location_id']) {
            $query->where(function ($parkingFilterQuery) use ($filters) {
                $parkingFilterQuery->whereHas('parkingLocations', function ($parkingQuery) use ($filters) {
                    $parkingQuery->whereKey($filters['parking_location_id']);
                })->orWhere('parking_location_id', $filters['parking_location_id']);
            });
        }

        if ($filters['search']) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->whereHas('employee', function ($employeeQuery) use ($search) {
                    $employeeQuery->where('nik', 'like', '%' . $search . '%')
                        ->orWhere('name', 'like', '%' . $search . '%');
                })->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                    $vehicleQuery->where('plate_number', 'like', '%' . $search . '%');
                });
            });
        }

        if ($filters['qr_status']) {
            $this->applyQrStatusFilter($query, $filters['qr_status']);
        }
    }

    private function applyQrStatusFilter($query, string $qrStatus): void
    {
        if ($qrStatus === 'active') {
            $query->whereHas('activeToken', function ($tokenQuery) {
                $tokenQuery->where(function ($dateQuery) {
                    $dateQuery->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now());
                });
            });
        }

        if ($qrStatus === 'expired') {
            $query->whereHas('activeToken', function ($tokenQuery) {
                $tokenQuery->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            });
        }

        if ($qrStatus === 'missing') {
            $query->whereDoesntHave('activeToken');
        }

        if ($qrStatus === 'revoked') {
            $query->whereDoesntHave('activeToken')
                ->whereHas('tokens', function ($tokenQuery) {
                    $tokenQuery->where('status', PermitToken::STATUS_REVOKED);
                });
        }
    }

    private function statusOptions(): array
    {
        return [
            VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review',
            VehiclePermit::STATUS_ACTIVE => 'Aktif',
            VehiclePermit::STATUS_DRAFT => 'Draft',
            VehiclePermit::STATUS_SUSPENDED => 'Ditangguhkan',
            VehiclePermit::STATUS_EXPIRED => 'Kadaluwarsa',
            VehiclePermit::STATUS_REVOKED => 'Dicabut',
        ];
    }

    private function qrStatusOptions(): array
    {
        return [
            'missing' => 'Belum dibuat',
            'active' => 'QR Aktif',
            'expired' => 'QR Kadaluwarsa',
            'revoked' => 'QR Dicabut',
        ];
    }

    private function colorOptions(): array
    {
        return VehiclePermit::query()
            ->whereNotNull('permit_color')
            ->where('permit_color', '!=', '')
            ->orderBy('permit_color')
            ->distinct()
            ->pluck('permit_color', 'permit_color')
            ->all();
    }

    private function statusSummary(): array
    {
        return VehiclePermit::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }
}
