<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class NoControlCharacters implements Rule
{
    public function passes($attribute, $value)
    {
        return is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }

    public function message()
    {
        return 'Kolom :attribute mengandung karakter yang tidak diizinkan.';
    }
}
