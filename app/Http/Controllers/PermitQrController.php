<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        return view('permits.qr.batch-print', [
            'cards' => $this->cardsForBatchPrint($permits),
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

    private function cardsForBatchPrint(Collection $permits): Collection
    {
        return $permits->map(function (VehiclePermit $permit) {
            $plainToken = $this->tokens->plainTokenForDisplay($permit->activeToken);

            return [
                'name' => optional($permit->employee)->name ?: '-',
                'nik' => optional($permit->employee)->nik ?: '-',
                'qrSvg' => $plainToken ? $this->tokens->renderSvg($plainToken) : null,
            ];
        })->filter(function (array $card) {
            return $card['qrSvg'] !== null;
        })->values();
    }

    private function batchPrintFilters(Request $request): array
    {
        return [
            'department' => $this->nullableString($request->query('department')),
            'division' => $this->nullableString($request->query('division')),
            'permit_color' => $this->nullableString($request->query('permit_color')),
        ];
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
