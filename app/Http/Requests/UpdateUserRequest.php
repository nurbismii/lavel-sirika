<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\NoControlCharacters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()
            && $this->user()->isActive()
            && $this->user()->hasRole(User::ROLE_SUPER_ADMIN);
    }

    public function rules()
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                new NoControlCharacters(),
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user ? $user->id : null),
            ],
            'role' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'status' => ['required', Rule::in(array_keys(User::statusOptions()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
