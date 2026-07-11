<?php

namespace App\Services\Imports;

use App\Imports\PermitExcelArrayImport;
use Illuminate\Auth\Access\AuthorizationException;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\RoadSegment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class PermitExcelImportService
{
    private $headerMapper;
    private $normalizer;
    private $fileValidator;

    public function __construct(
        PermitImportHeaderMapper $headerMapper,
        PermitImportRowNormalizer $normalizer,
        PermitImportFileValidator $fileValidator
    ) {
        $this->headerMapper = $headerMapper;
        $this->normalizer = $normalizer;
        $this->fileValidator = $fileValidator;
    }

    public function preview(UploadedFile $file, User $user): ImportBatch
    {
        $this->authorizePreview($user);
        $this->fileValidator->validate($file);

        $batch = ImportBatch::create([
            'filename' => $file->getClientOriginalName(),
            'uploaded_by' => $user->id,
            'status' => ImportBatch::STATUS_DRAFT,
        ]);

        try {
            $storedPath = $this->storeFile($file);
            $sheets = Excel::toArray(new PermitExcelArrayImport(), $storedPath, 'local');
            $rows = $sheets[0] ?? [];

            if ($rows === []) {
                throw new \InvalidArgumentException('Sheet Excel kosong.');
            }

            $maxRows = (int) config('sirika.import.max_rows', 5000);
            if (count($rows) > $maxRows) {
                throw new \InvalidArgumentException(
                    'Sheet Excel maksimal ' . $maxRows . ' baris termasuk header.'
                );
            }

            $header = $this->headerMapper->findHeader($rows);
            $activeRouteCodes = RoadSegment::query()
                ->where('status', 'active')
                ->pluck('code')
                ->map(function ($code) {
                    return strtoupper((string) $code);
                })
                ->all();

            $counts = [
                ImportRow::STATUS_VALID => 0,
                ImportRow::STATUS_INVALID => 0,
                ImportRow::STATUS_NEEDS_REVIEW => 0,
            ];

            DB::transaction(function () use ($batch, $rows, $header, $activeRouteCodes, &$counts) {
                foreach ($rows as $index => $row) {
                    if ($index <= $header['row_index']) {
                        continue;
                    }

                    if ($this->isEmptyRow($row)) {
                        continue;
                    }

                    $normalized = $this->normalizer->normalize(
                        $row,
                        $header['columns'],
                        $activeRouteCodes,
                        $index + 1
                    );

                    $counts[$normalized['status']]++;

                    ImportRow::create([
                        'import_batch_id' => $batch->id,
                        'row_number' => $normalized['row_number'],
                        'status' => $normalized['status'],
                        'raw_data' => $normalized['raw_data'],
                        'normalized_data' => $normalized['normalized_data'],
                        'errors' => $normalized['errors'],
                        'warnings' => $normalized['warnings'],
                    ]);
                }

                $batch->update([
                    'total_rows' => array_sum($counts),
                    'success_rows' => $counts[ImportRow::STATUS_VALID],
                    'failed_rows' => $counts[ImportRow::STATUS_INVALID],
                    'review_rows' => $counts[ImportRow::STATUS_NEEDS_REVIEW],
                    'status' => ImportBatch::STATUS_PREVIEWED,
                    'error_summary' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $batch->update([
                'status' => ImportBatch::STATUS_FAILED,
                'error_summary' => $exception->getMessage(),
            ]);
        }

        return $batch->fresh();
    }

    protected function storeFile(UploadedFile $file): string
    {
        $directory = 'imports/' . date('Y/m');
        $extension = $file->getClientOriginalExtension() ?: 'xlsx';
        $filename = (string) Str::uuid() . '.' . $extension;

        $storedPath = $file->storeAs($directory, $filename, 'local');

        if (!is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('Gagal menyimpan file upload ke private storage.');
        }

        return $storedPath;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function authorizePreview(User $user): void
    {
        if (
            $user->isActive()
            && $user->hasAnyRole([User::ROLE_ADMIN_HR, User::ROLE_SUPER_ADMIN])
        ) {
            return;
        }

        throw new AuthorizationException('User tidak diizinkan melakukan preview import Excel.');
    }
}
