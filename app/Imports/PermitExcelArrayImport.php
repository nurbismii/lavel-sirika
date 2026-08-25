<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class PermitExcelArrayImport implements ToArray
{
    public function array(array $array): void
    {
        // Excel::toArray returns the parsed sheets; this concern only receives
        // the callback required by Laravel Excel's import contract.
    }
}
