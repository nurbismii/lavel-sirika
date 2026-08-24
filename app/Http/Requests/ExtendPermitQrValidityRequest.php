<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtendPermitQrValidityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valid_until' => [
                'required',
                'date_format:Y-m-d',
                'after:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'valid_until.required' => 'Tanggal masa berlaku baru wajib diisi.',
            'valid_until.date_format' => 'Format tanggal masa berlaku tidak valid.',
            'valid_until.after' => 'Tanggal masa berlaku baru harus setelah hari ini.',
        ];
    }
}
