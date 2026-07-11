<?php

namespace App\Services\Imports;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class PermitImportFileValidator
{
    public function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Upload file Excel tidak valid.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, config('sirika.import.extensions', []), true)) {
            throw new InvalidArgumentException('Ekstensi file Excel tidak didukung.');
        }

        $kilobytes = (int) ceil(((int) $file->getSize()) / 1024);
        if ($kilobytes > (int) config('sirika.import.max_file_kilobytes', 10240)) {
            throw new InvalidArgumentException('Ukuran file Excel maksimal 10 MB.');
        }

        $mimeType = (string) $file->getMimeType();
        if (! in_array($mimeType, config('sirika.import.mime_types', []), true)) {
            throw new InvalidArgumentException('Tipe file Excel tidak didukung.');
        }
    }
}
