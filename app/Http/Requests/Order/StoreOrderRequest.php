<?php

namespace App\Http\Requests\Order;

class StoreOrderRequest extends QuoteOrderRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'delivery_address' => ['nullable', 'string', 'max:1000', 'required_if:delivery_type,delivery'],
            'comments' => ['nullable', 'string', 'max:1500'],
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{8,30}$/'],
            'idempotency_key' => ['required', 'uuid'],
            'quote_token' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_address.required_if' => 'L’adresse de livraison est obligatoire pour une commande en livraison.',
        ];
    }
}
