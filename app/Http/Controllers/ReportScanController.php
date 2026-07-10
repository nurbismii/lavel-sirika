<?php

namespace App\Http\Controllers;

use App\Exports\ScanReportExport;
use App\Http\Requests\ReportScanRequest;
use App\Services\Reports\ScanReportQuery;
use Maatwebsite\Excel\Facades\Excel;

class ReportScanController extends Controller
{
    public function index(ReportScanRequest $request, ScanReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());

        return view('reports.scans.index', [
            'pageTitle' => 'Laporan Scan',
            'pageDescription' => 'Laporan aktivitas scan QR kendaraan berdasarkan tanggal, hasil scan, dan scanner.',
            'filters' => $filters,
            'scanLogs' => $reports->query($filters)->paginate(25)->appends($request->query()),
            'reports' => $reports,
            'resultOptions' => $reports->resultOptions(),
            'scannerOptions' => $reports->scannerOptions(),
        ]);
    }

    public function export(ReportScanRequest $request, ScanReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());
        $reports->assertExportRange($filters);

        $filename = 'sirika-laporan-scan-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new ScanReportExport($reports, $filters), $filename);
    }
}
