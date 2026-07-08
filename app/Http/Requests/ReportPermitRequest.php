<?php

namespace App\Http\Requests;

use App\Models\VehiclePermit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPermitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => [
                'nullable',
                Rule::in([
                    VehiclePermit::STATUS_DRAFT,
                    VehiclePermit::STATUS_NEEDS_REVIEW,
                    VehiclePermit::STATUS_ACTIVE,
                    VehiclePermit::STATUS_SUSPENDED,
                    VehiclePermit::STATUS_EXPIRED,
                    VehiclePermit::STATUS_REVOKED,
                ]),
            ],
            'qr_status' => ['nullable', Rule::in(['missing', 'active', 'expired', 'revoked'])],
            'permit_color' => ['nullable', 'string', 'max:32'],
            'parking_location_id' => ['nullable', 'integer', 'exists:parking_locations,id'],
            'source' => ['nullable', 'string', 'max:32'],
            'review_status' => ['nullable', Rule::in(['pending', 'reviewed'])],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
