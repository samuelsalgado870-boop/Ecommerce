<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('pedidos.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'clave_idempotencia' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:id_producto,cantidad'],
            'items.*.id_producto' => ['required', 'integer', 'distinct', 'exists:productos,id_producto'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'id_usuario' => ['prohibited'], 'total' => ['prohibited'], 'estado' => ['prohibited'],
        ];
    }
}
