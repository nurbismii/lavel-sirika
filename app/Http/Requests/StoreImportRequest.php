<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null && $this->user()->canAccessRoute('imports.store');
    }

    public function rules()
    {
        $maxKilobytes = (int) config('sirika.import.max_file_kilobytes', 10240);

        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:' . $maxKilobytes],
        ];
    }

    public function messages()
    {
        return [
            'file.required' => 'File Excel wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
