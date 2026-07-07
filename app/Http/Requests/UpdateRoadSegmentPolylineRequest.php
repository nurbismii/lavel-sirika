<?php

namespace App\Http\Requests;

use App\Support\RouteMapConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRoadSegmentPolylineRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'save_mode' => ['required', 'in:draft,complete'],
            'points_json' => ['required', 'json'],
            'points' => ['array', 'max:200'],
            'points.*.x' => ['required', 'numeric', 'min:0', 'max:' . RouteMapConfig::width()],
            'points.*.y' => ['required', 'numeric', 'min:0', 'max:' . RouteMapConfig::height()],
        ];
    }

    protected function prepareForValidation()
    {
        $decoded = json_decode((string) $this->input('points_json'), true);

        $this->merge([
            'points' => is_array($decoded) ? $decoded : [],
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function (Validator $validator) {
            $points = $this->input('points', []);

            if (count($points) === 0) {
                $validator->errors()->add('points', 'Minimal satu titik diperlukan. Gunakan reset untuk menghapus koordinat.');
            }

            if ($this->input('save_mode') === 'complete' && count($points) < 2) {
                $validator->errors()->add('points', 'Status lengkap membutuhkan minimal dua titik.');
            }
        });
    }
}
