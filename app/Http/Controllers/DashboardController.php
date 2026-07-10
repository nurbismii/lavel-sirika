<?php

namespace App\Http\Controllers;

use App\Models\PermitToken;
use App\Models\RoadSegment;
use App\Models\ScanLog;
use App\Models\VehiclePermit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();

        return view('dashboard.index', [
            'pageTitle' => 'Dashboard SIRIKA',
            'pageDescription' => 'Ringkasan operasional izin kendaraan, QR, scan, dan laporan.',
            'activeRoadSegments' => RoadSegment::where('status', 'active')->count(),
            'activeUsers' => User::where('status', User::STATUS_ACTIVE)->count(),
            'activePermits' => VehiclePermit::where('status', VehiclePermit::STATUS_ACTIVE)->count(),
            'reviewPermits' => VehiclePermit::where('status', VehiclePermit::STATUS_NEEDS_REVIEW)->count(),
            'activeQrTokens' => PermitToken::where('status', PermitToken::STATUS_ACTIVE)
                ->where(function ($query) use ($now) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', $now);
                })
                ->count(),
            'expiredQrTokens' => PermitToken::where('status', PermitToken::STATUS_ACTIVE)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->count(),
            'todayScans' => ScanLog::whereDate('scanned_at', $now->toDateString())->count(),
            'todayInvalidScans' => ScanLog::whereDate('scanned_at', $now->toDateString())
                ->where('result', ScanLog::RESULT_INVALID)
                ->count(),
            'permitStatusSummary' => $this->permitStatusSummary(),
            'scanResultSummary' => $this->scanResultSummary($now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()),
            'activityFeed' => $this->activityFeed(),
        ]);
    }

    private function permitStatusSummary()
    {
        $labels = [
            VehiclePermit::STATUS_ACTIVE => 'Aktif',
            VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review',
            VehiclePermit::STATUS_SUSPENDED => 'Ditahan',
            VehiclePermit::STATUS_EXPIRED => 'Kedaluwarsa',
            VehiclePermit::STATUS_REVOKED => 'Dicabut',
            VehiclePermit::STATUS_DRAFT => 'Draft',
        ];

        $counts = VehiclePermit::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect($labels)->map(function ($label, $status) use ($counts) {
            return [
                'status' => $status,
                'label' => $label,
                'total' => (int) ($counts[$status] ?? 0),
            ];
        })->values();
    }

    private function scanResultSummary($from, $to)
    {
        $labels = [
            ScanLog::RESULT_VALID => 'Valid',
            ScanLog::RESULT_INVALID => 'Invalid',
            ScanLog::RESULT_EXPIRED => 'Kedaluwarsa',
            ScanLog::RESULT_REVOKED => 'Dicabut',
            ScanLog::RESULT_INACTIVE => 'Tidak Aktif',
        ];

        $counts = ScanLog::query()
            ->whereBetween('scanned_at', [$from, $to])
            ->select('result', DB::raw('COUNT(*) as total'))
            ->groupBy('result')
            ->pluck('total', 'result');

        return collect($labels)->map(function ($label, $result) use ($counts) {
            return [
                'result' => $result,
                'label' => $label,
                'total' => (int) ($counts[$result] ?? 0),
            ];
        })->values();
    }

    private function activityFeed()
    {
        $reviews = VehiclePermit::with(['employee', 'vehicle', 'reviewer'])
            ->whereNotNull('reviewed_at')
            ->latest('reviewed_at')
            ->limit(5)
            ->get(['id', 'employee_id', 'vehicle_id', 'status', 'reviewed_by', 'reviewed_at'])
            ->map(function (VehiclePermit $permit) {
                return [
                    'type' => 'Review izin',
                    'title' => $this->permitTitle($permit),
                    'description' => $this->permitDescription($permit),
                    'meta' => optional($permit->reviewer)->name ?: 'System',
                    'occurred_at' => $permit->reviewed_at,
                ];
            });

        $tokens = PermitToken::with(['permit.employee', 'permit.vehicle'])
            ->latest()
            ->limit(5)
            ->get(['id', 'vehicle_permit_id', 'status', 'expires_at', 'created_at'])
            ->map(function (PermitToken $token) {
                $expiresAt = $token->expires_at
                    ? 'Berlaku sampai ' . $token->expires_at->format('d M Y')
                    : 'Tanpa tanggal kedaluwarsa';

                return [
                    'type' => 'QR dibuat',
                    'title' => $this->permitTitle($token->permit),
                    'description' => $this->permitDescription($token->permit) . ' - ' . $expiresAt,
                    'meta' => ucfirst($token->status),
                    'occurred_at' => $token->created_at,
                ];
            });

        $scans = ScanLog::with(['permit.employee', 'permit.vehicle', 'scanner'])
            ->latest('scanned_at')
            ->limit(5)
            ->get(['id', 'permit_id', 'scanned_by', 'scanned_at', 'result', 'created_at'])
            ->map(function (ScanLog $scan) {
                return [
                    'type' => 'Scan QR',
                    'title' => $this->permitTitle($scan->permit),
                    'description' => $this->permitDescription($scan->permit),
                    'meta' => ucfirst($scan->result) . ' oleh ' . (optional($scan->scanner)->name ?: 'Scanner tidak diketahui'),
                    'occurred_at' => $scan->scanned_at ?: $scan->created_at,
                ];
            });

        return $reviews
            ->concat($tokens)
            ->concat($scans)
            ->sortByDesc(function ($activity) {
                return $activity['occurred_at'] ? $activity['occurred_at']->getTimestamp() : 0;
            })
            ->take(10)
            ->values();
    }

    private function permitTitle($permit)
    {
        if (! $permit) {
            return 'Izin tidak ditemukan';
        }

        return optional($permit->employee)->name ?: 'Izin #' . $permit->id;
    }

    private function permitDescription($permit)
    {
        if (! $permit) {
            return '-';
        }

        $parts = [];

        if ($permit->vehicle) {
            $parts[] = $permit->vehicle->plate_number;
        }

        if ($permit->status) {
            $parts[] = 'Status ' . $permit->status;
        }

        return $parts ? implode(' - ', $parts) : '-';
    }
}
