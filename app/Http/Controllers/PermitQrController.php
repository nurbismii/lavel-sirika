<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtendPermitQrValidityRequest;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function batchPrint(Request $request)
    {
        $filters = $this->batchPrintFilters($request);

        $query = VehiclePermit::query()
            ->with([
                'employee:id,nik,name',
                'vehicle:id,plate_number',
                'activeToken' => function ($query) {
                    $query->select([
                        'permit_tokens.id',
                        'permit_tokens.vehicle_permit_id',
                        'permit_tokens.token_encrypted',
                        'permit_tokens.status',
                        'permit_tokens.expires_at',
                    ]);
                },
            ]);

        $this->applyActiveQrConstraint($query);

        if ($filters['plates']) {
            $query->whereHas('vehicle', function ($vehicleQuery) use ($filters) {
                $this->applyPlateFilter($vehicleQuery, $filters['plates']);
            });
        }

        if ($filters['department']) {
            $query->whereHas('employee', function ($employeeQuery) use ($filters) {
                $employeeQuery->where('department', $filters['department']);
            });
        }

        if ($filters['division']) {
            $query->whereHas('employee', function ($employeeQuery) use ($filters) {
                $employeeQuery->where('division', $filters['division']);
            });
        }

        if ($filters['permit_color']) {
            $query->where('permit_color', $filters['permit_color']);
        }

        $permits = $query->orderBy('id')->get();
        $cards = $this->cardsForBatchPrint($permits);
        $missingPlates = [];
        $unavailablePlates = [];

        if ($filters['plates']) {
            $vehicleQuery = Vehicle::query();
            $this->applyPlateFilter($vehicleQuery, $filters['plates']);
            $existingPlates = $vehicleQuery->pluck('plate_number')->map(fn ($plate) => $this->normalizePlate($plate))->all();
            $printedPlates = $cards->pluck('plate_number')->map(fn ($plate) => $this->normalizePlate($plate))->all();
            $missingPlates = array_values(array_diff($filters['plates'], $existingPlates));
            $unavailablePlates = array_values(array_diff($filters['plates'], $missingPlates, $printedPlates));
        }

        return view('permits.qr.batch-print', [
            'cards' => $cards,
            'missingPlates' => $missingPlates,
            'unavailablePlates' => $unavailablePlates,
            'filters' => $filters,
            'departments' => $this->batchPrintEmployeeOptions('department'),
            'divisions' => $this->batchPrintEmployeeOptions('division'),
            'permitColors' => $this->batchPrintColorOptions(),
        ]);
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
            $result = $this->tokens->activeForPermit($permit);
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

    public function extend(ExtendPermitQrValidityRequest $request, VehiclePermit $permit)
    {
        try {
            $result = $this->tokens->extendValidityForPermit(
                $permit,
                $request->validated()['valid_until']
            );
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWithError($exception->getMessage());
        }

        Log::info('Permit QR validity extended.', [
            'vehicle_permit_id' => $permit->id,
            'permit_token_id' => $result['permit_token']->id,
            'extended_by' => $request->user()->id,
            'old_permit_expiry' => optional($result['old_permit_expiry'])->toDateString(),
            'old_token_expiry' => $result['old_token_expiry']->toDateTimeString(),
            'new_expiry' => $result['new_expiry']->toDateTimeString(),
        ]);

        return redirect()
            ->route('permits.qr.show', $permit)
            ->with('status', 'Masa berlaku QR berhasil diperpanjang tanpa mengubah kode QR.');
    }

    private function redirectWithError(string $message)
    {
        return redirect()
            ->back()
            ->with('error', $message);
    }

    private function cardsForBatchPrint(Collection $permits): Collection
    {
        return $permits->map(function (VehiclePermit $permit) {
            $plainToken = $this->tokens->plainTokenForDisplay($permit->activeToken);

            return [
                'name' => optional($permit->employee)->name ?: '-',
                'nik' => optional($permit->employee)->nik ?: '-',
                'plate_number' => optional($permit->vehicle)->plate_number ?: '-',
                'qrSvg' => $plainToken ? $this->tokens->renderSvg($plainToken) : null,
            ];
        })->filter(function (array $card) {
            return $card['qrSvg'] !== null;
        })->values();
    }

    private function batchPrintFilters(Request $request): array
    {
        $validated = $request->validate([
            'department' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'permit_color' => ['nullable', 'string', 'max:255'],
            'plate_list' => ['nullable', 'string', 'max:51000'],
        ]);
        $plateList = trim($validated['plate_list'] ?? '');
        $plates = collect(preg_split('/[\r\n\t,;]+/u', $plateList, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($plate) => $this->normalizePlate($plate))
            ->filter(fn ($plate) => $plate !== '')
            ->unique()->values()->all();

        if ($plateList !== '' && $plates === []) {
            throw ValidationException::withMessages(['plate_list' => 'Masukkan setidaknya satu plat nomor yang valid.']);
        }
        if (count($plates) > 500) {
            throw ValidationException::withMessages(['plate_list' => 'Maksimal 500 plat nomor unik per proses cetak.']);
        }
        foreach ($plates as $plate) {
            if (mb_strlen($plate) > 100) {
                throw ValidationException::withMessages(['plate_list' => 'Setiap plat nomor maksimal 100 karakter.']);
            }
        }

        return [
            'department' => $this->nullableString($validated['department'] ?? null),
            'division' => $this->nullableString($validated['division'] ?? null),
            'permit_color' => $this->nullableString($validated['permit_color'] ?? null),
            'plate_list' => $plateList,
            'plates' => $plates,
        ];
    }

    private function normalizePlate(string $plate): string
    {
        return strtoupper(str_replace(' ', '', trim($plate)));
    }

    private function applyPlateFilter($query, array $plates): void
    {
        $query->whereIn(DB::raw("UPPER(REPLACE(plate_number, ' ', ''))"), $plates);
    }

    private function batchPrintEmployeeOptions(string $column): array
    {
        return Employee::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->whereHas('permits', function ($query) {
                $this->applyActiveQrConstraint($query);
            })
            ->orderBy($column)
            ->distinct()
            ->pluck($column, $column)
            ->all();
    }

    private function batchPrintColorOptions(): array
    {
        $query = VehiclePermit::query()
            ->whereNotNull('permit_color')
            ->where('permit_color', '!=', '');

        $this->applyActiveQrConstraint($query);

        return $query->orderBy('permit_color')
            ->distinct()
            ->pluck('permit_color', 'permit_color')
            ->all();
    }

    private function applyActiveQrConstraint($query): void
    {
        $query->where('vehicle_permits.status', VehiclePermit::STATUS_ACTIVE)
            ->whereHas('activeToken', function ($tokenQuery) {
                $tokenQuery->where(function ($dateQuery) {
                    $dateQuery->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now());
                });
            });
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
