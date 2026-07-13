<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoadSegmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('road_segments', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'start_location' => ['required', 'string', 'max:255'],
            'end_location' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }
}
