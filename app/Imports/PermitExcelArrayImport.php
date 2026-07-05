<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class PermitExcelArrayImport implements ToArray
{
    public function array(array $array)
    {
        return $array;
    }
}
