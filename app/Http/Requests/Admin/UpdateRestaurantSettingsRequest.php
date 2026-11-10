<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_fee' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:99999.999'],
            'minimum_order_amount' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999.999'],
            'ordering_enabled' => ['required', 'boolean'], 'delivery_enabled' => ['required', 'boolean'],
            'delivery_postcodes' => ['present', 'array', 'max:500', 'min:'.($this->boolean('delivery_enabled') ? '1' : '0')],
            'delivery_postcodes.*' => ['required', 'string', 'regex:/^\d{4}$/', 'distinct'],
            'opening_days' => ['present', 'array', 'max:7'], 'opening_days.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'opens_at' => ['required', 'date_format:H:i'], 'closes_at' => ['required', 'date_format:H:i', 'different:opens_at'],
            'closed_dates' => ['present', 'array', 'max:366'], 'closed_dates.*' => ['required', 'date_format:Y-m-d', 'distinct'],
        ];
    }
}
