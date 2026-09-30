<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('usuario'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:150',
                Rule::unique('usuarios', 'email')->ignore($this->route('usuario')->getKey(), 'id_usuario')],
            'password' => ['sometimes', 'required', 'string', 'max:72', 'confirmed', Password::min(12)->letters()->numbers()],
            'current_password' => ['required_with:password', 'string'],
            'id_rol' => ['prohibited'], 'roles' => ['prohibited'], 'activo' => ['prohibited'],
            'password_hash' => ['prohibited'], 'intentos_fallidos' => ['prohibited'],
            'bloqueado_hasta' => ['prohibited'], 'ultimo_acceso' => ['prohibited'],
        ];
    }
}
