<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateRoadSegmentRequest extends StoreRoadSegmentRequest
{
    public function rules()
    {
        return [
            'code' => [
                'required',
                'string',
                'max:16',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('road_segments', 'code')->ignore($this->route('roadSegment')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'start_location' => ['required', 'string', 'max:255'],
            'end_location' => ['required', 'string', 'max:255'],
        ];
    }
}
