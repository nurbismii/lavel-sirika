<?php

namespace App\Http\Requests;

use App\Models\VehiclePermit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermitDataRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(): array
    {
        /** @var VehiclePermit $permit */
        $permit = $this->route('permit');

        return [
            'nik' => [
                'required',
                'string',
                Rule::unique('employees', 'nik')->ignore($permit->employee_id),
            ],
            'name' => ['required', 'string'],
            'plate_number' => [
                'required',
                'string',
                Rule::unique('vehicles', 'plate_number')->ignore($permit->vehicle_id),
            ],
            'parking_location_ids' => ['required', 'array', 'min:1'],
            'parking_location_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('parking_locations', 'id')->where('status', 'active'),
            ],
            'road_segment_ids' => ['required', 'array', 'min:1'],
            'road_segment_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('road_segments', 'id')->where('status', 'active'),
            ],
        ];
    }
}
