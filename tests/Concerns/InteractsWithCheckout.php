<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\RestaurantSetting;
use Carbon\Carbon;
use Illuminate\Support\Str;

trait InteractsWithCheckout
{
    protected function configureCheckout(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 13:00:00', 'Africa/Tunis'));
        RestaurantSetting::query()->updateOrCreate(['key' => 'delivery_enabled'], ['value' => 'true']);
        RestaurantSetting::query()->updateOrCreate(['key' => 'delivery_postcodes'], ['value' => '["3062"]']);
    }

    protected function checkoutPayload(array $overrides = []): array
    {
        $payload = array_replace([
            'delivery_type' => 'takeaway', 'delivery_address' => null, 'delivery_postcode' => null,
            'contact_phone' => '+21628820982', 'comments' => null,
            'items' => [['product_id' => Product::query()->firstOrFail()->id, 'quantity' => 1]],
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
        $quote = $this->postJson('/api/orders/quote', $payload)->assertOk()->json('quote');

        return [...$payload, 'quote_token' => $quote['quote_token']];
    }
}
