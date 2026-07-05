<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyScanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'token' => ['required', 'string', 'min:1', 'max:255'],
            'device_info' => ['nullable', 'string', 'max:500'],
        ];
    }
}
