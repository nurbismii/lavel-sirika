<?php

namespace App\Http\Requests;

use App\Models\ScanLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportScanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $dateRequirement = $this->routeIs('reports.scans.export') ? 'required' : 'nullable';

        return [
            'date_from' => [$dateRequirement, 'date'],
            'date_to' => [$dateRequirement, 'date', 'after_or_equal:date_from'],
            'result' => [
                'nullable',
                Rule::in([
                    ScanLog::RESULT_VALID,
                    ScanLog::RESULT_EXPIRED,
                    ScanLog::RESULT_REVOKED,
                    ScanLog::RESULT_INACTIVE,
                    ScanLog::RESULT_INVALID,
                ]),
            ],
            'scanner_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages()
    {
        return [
            'date_from.required' => 'Tanggal awal wajib diisi untuk export laporan scan.',
            'date_to.required' => 'Tanggal akhir wajib diisi untuk export laporan scan.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'result.in' => 'Hasil scan tidak valid.',
            'scanner_id.exists' => 'Scanner tidak ditemukan.',
        ];
    }
}
