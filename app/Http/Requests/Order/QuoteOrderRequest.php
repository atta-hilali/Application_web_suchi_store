<?php

namespace App\Http\Requests\Order;

use App\Enums\DeliveryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class QuoteOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_type' => ['required', new Enum(DeliveryType::class)],
            'delivery_postcode' => ['nullable', 'required_if:delivery_type,delivery', 'string', 'regex:/^\d{4}$/'],
            'items' => ['required', 'array', 'min:1', 'max:'.config('restaurant.max_items')],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('restaurant.max_quantity')],
        ];
    }
}
