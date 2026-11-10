<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRestaurantSettingsRequest;
use App\Models\RestaurantSetting;
use App\Services\HeroMedia;
use App\Services\RestaurantRules;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RestaurantSettingsController extends Controller
{
    public function show(RestaurantRules $rules): JsonResponse
    {
        $settings = $rules->settings();

        return response()->json(['settings' => [...$settings, 'hero_media' => app(HeroMedia::class)->current(), 'is_open' => $rules->isOpen($settings),
            'server_time' => now('Africa/Tunis')->toIso8601String()]]);
    }

    public function update(UpdateRestaurantSettingsRequest $request, RestaurantRules $rules): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated() as $key => $value) {
                if (in_array($key, ['delivery_fee', 'minimum_order_amount'], true)) {
                    $value = Money::decimal(Money::millimes($value));
                }
                RestaurantSetting::query()->updateOrCreate(['key' => $key], [
                    'value' => is_array($value) || is_bool($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value,
                ]);
            }
        });

        return $this->show($rules);
    }
}
