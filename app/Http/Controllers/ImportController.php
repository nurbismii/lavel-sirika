<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\ImportBatch;
use App\Services\Imports\PermitExcelImportService;
use App\Services\Imports\PermitImportCommitService;
use Illuminate\Http\Request;
use RuntimeException;

class ImportController extends Controller
{
    public function index()
    {
        return view('imports.index', [
            'batches' => ImportBatch::with('uploader')->latest()->paginate(15),
        ]);
    }

    public function store(StoreImportRequest $request, PermitExcelImportService $service)
    {
        $batch = $service->preview($request->file('file'), $request->user());

        if ($batch->status === ImportBatch::STATUS_FAILED) {
            return redirect()
                ->route('imports.show', $batch)
                ->with('status', 'File berhasil diterima, tetapi parsing gagal. Periksa detail error batch.');
        }

        return redirect()
            ->route('imports.show', $batch)
            ->with('status', 'File berhasil diproses. Periksa preview sebelum commit data.');
    }

    public function show(Request $request, ImportBatch $importBatch)
    {
        $status = $request->query('status');

        $rowsQuery = $importBatch->rows()->orderBy('row_number');
        if (in_array($status, ['valid', 'invalid', 'needs_review', 'committed'], true)) {
            $rowsQuery->where('status', $status);
        }

        return view('imports.show', [
            'batch' => $importBatch->load('uploader'),
            'rows' => $rowsQuery->paginate(50)->appends($request->query()),
            'selectedStatus' => $status,
        ]);
    }

    public function commit(ImportBatch $importBatch, PermitImportCommitService $service)
    {
        try {
            $service->commit($importBatch);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('imports.show', $importBatch)
                ->with('status', $exception->getMessage());
        }

        return redirect()
            ->route('imports.show', $importBatch)
            ->with('status', 'Batch import berhasil dikomit.');
    }
}
