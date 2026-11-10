<?php

namespace Database\Seeders;

use App\Models\RestaurantSetting;
use Illuminate\Database\Seeder;

class RestaurantSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'restaurant_name' => 'Miya Sushi',
            'currency' => 'TND',
            'delivery_fee' => '0',
            'minimum_order_amount' => '0',
            'opening_hours' => 'Lun-Dim 11:30-22:30',
            'contact_phone' => '+216 28 820 982',
            ...array_map(fn ($value) => is_array($value) || is_bool($value) ? json_encode($value) : $value, config('restaurant.defaults')),
        ];

        foreach ($settings as $key => $value) {
            RestaurantSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
