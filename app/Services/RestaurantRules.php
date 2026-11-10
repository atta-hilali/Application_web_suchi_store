<?php

namespace App\Services;

use App\Models\RestaurantSetting;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RestaurantRules
{
    public function settings(): array
    {
        $stored = RestaurantSetting::query()->pluck('value', 'key');
        $settings = config('restaurant.defaults');
        foreach ($settings as $key => $default) {
            if (! $stored->has($key)) {
                continue;
            }
            $value = $stored[$key];
            $settings[$key] = is_array($default) ? (json_decode($value, true) ?? $default)
                : (is_bool($default) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : $value);
        }
        $settings['opening_days'] = array_map('intval', $settings['opening_days']);

        return [...$settings, 'currency' => 'TND', 'timezone' => 'Africa/Tunis',
            'max_quantity' => config('restaurant.max_quantity'), 'max_items' => config('restaurant.max_items')];
    }

    public function isOpen(?array $settings = null): bool
    {
        $settings ??= $this->settings();
        $now = CarbonImmutable::now('Africa/Tunis');
        if (! $settings['ordering_enabled'] || in_array($now->toDateString(), $settings['closed_dates'], true)) {
            return false;
        }
        foreach ([$now->startOfDay(), $now->subDay()->startOfDay()] as $day) {
            if (! in_array($day->isoWeekday(), $settings['opening_days'], true)
                || in_array($day->toDateString(), $settings['closed_dates'], true)) {
                continue;
            }
            $opens = $day->setTimeFromTimeString($settings['opens_at']);
            $closes = $day->setTimeFromTimeString($settings['closes_at']);
            if ($closes->lessThanOrEqualTo($opens)) {
                $closes = $closes->addDay();
            }
            if ($now->greaterThanOrEqualTo($opens) && $now->lessThan($closes)) {
                return true;
            }
        }

        return false;
    }

    public function assertCanOrder(array $payload, array $settings): void
    {
        if (! $this->isOpen($settings)) {
            throw ValidationException::withMessages(['restaurant' => 'Le restaurant est fermé aux commandes. Consultez les horaires (heure de Tunis).']);
        }
        if ($payload['delivery_type'] === 'delivery') {
            if (! $settings['delivery_enabled'] || $settings['delivery_postcodes'] === []) {
                throw ValidationException::withMessages(['delivery_type' => 'La livraison est indisponible. Choisissez le retrait sur place.']);
            }
            if (! in_array($payload['delivery_postcode'] ?? '', $settings['delivery_postcodes'], true)) {
                throw ValidationException::withMessages(['delivery_postcode' => 'Ce code postal est en dehors de notre zone de livraison.']);
            }
        }
    }
}
