<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermitReviewRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $activating = $this->routeIs('permits.review.activate');

        return [
            'parking_location_ids' => [
                $activating ? 'required' : 'nullable',
                'array',
                $activating ? 'min:1' : 'nullable',
            ],
            'parking_location_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('parking_locations', 'id')->where('status', 'active'),
            ],
            'route_raw' => [
                $activating ? 'required' : 'nullable',
                'string',
                'max:5000',
            ],
            'review_note' => [
                $activating ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages()
    {
        return [
            'parking_location_ids.required' => 'Pilih minimal satu lokasi parkir sebelum aktivasi izin.',
            'parking_location_ids.min' => 'Pilih minimal satu lokasi parkir sebelum aktivasi izin.',
            'parking_location_ids.*.exists' => 'Pilih lokasi parkir aktif yang valid.',
            'route_raw.required' => 'Rute kendaraan kosong.',
            'route_raw.max' => 'Rute kendaraan maksimal 5000 karakter.',
            'review_note.required' => 'Catatan review wajib diisi sebelum aktivasi izin.',
            'review_note.max' => 'Catatan review maksimal 2000 karakter.',
        ];
    }

    protected function prepareForValidation()
    {
        if (! $this->has('parking_location_ids') && $this->filled('parking_location_id')) {
            $this->merge([
                'parking_location_ids' => [$this->input('parking_location_id')],
            ]);
        }
    }
}
