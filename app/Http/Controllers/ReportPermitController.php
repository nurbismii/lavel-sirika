<?php

namespace App\Http\Controllers;

use App\Exports\PermitReportExport;
use App\Exports\PermitNeedsReviewExport;
use App\Http\Requests\ReportPermitRequest;
use App\Models\ParkingLocation;
use App\Models\VehiclePermit;
use App\Services\Reports\PermitReportQuery;
use Maatwebsite\Excel\Facades\Excel;

class ReportPermitController extends Controller
{
    public function index(ReportPermitRequest $request, PermitReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());

        return view('reports.permits.index', [
            'pageTitle' => 'Laporan Izin',
            'pageDescription' => 'Laporan operasional izin kendaraan, status review, dan status QR.',
            'filters' => $filters,
            'permits' => $reports->query($filters)->paginate(25)->appends($request->query()),
            'reports' => $reports,
            'statusOptions' => $reports->statusOptions(),
            'qrStatusOptions' => $reports->qrStatusOptions(),
            'reviewStatusOptions' => $reports->reviewStatusOptions(),
            'colorOptions' => $reports->colorOptions(),
            'sourceOptions' => $reports->sourceOptions(),
            'parkingLocations' => ParkingLocation::query()->orderBy('code')->get(),
            'statusSummary' => $reports->statusSummary(),
        ]);
    }

    public function export(ReportPermitRequest $request, PermitReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());
        $filename = 'sirika-laporan-izin-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new PermitReportExport($reports, $filters), $filename);
    }

    public function exportNeedsReview(ReportPermitRequest $request, PermitReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());
        $filters['status'] = VehiclePermit::STATUS_NEEDS_REVIEW;

        return Excel::download(
            new PermitNeedsReviewExport($reports, $filters),
            'sirika-izin-perlu-review-' . now()->format('Ymd-His') . '.xlsx'
        );
    }
}
