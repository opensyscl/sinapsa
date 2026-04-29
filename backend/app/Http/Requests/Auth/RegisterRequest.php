<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace_name' => ['required', 'string', 'max:120'],
            'workspace_slug' => [
                'nullable',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                'unique:workspaces,slug',
            ],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'workspace_slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'workspace_slug.unique' => 'Ese slug de workspace ya está cogido.',
            'email.unique' => 'Ya existe una cuenta con ese email.',
        ];
    }
}
