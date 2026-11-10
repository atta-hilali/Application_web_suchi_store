<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\User;
use App\Services\RestaurantRules;
use Carbon\Carbon;
use Database\Seeders\RestaurantSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCheckout;
use Tests\TestCase;

class CheckoutSafeguardsTest extends TestCase
{
    use InteractsWithCheckout, RefreshDatabase;

    protected User $client;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->configureCheckout();
        $this->client = User::query()->where('role', 'client')->firstOrFail();
        $this->admin = User::query()->where('role', 'admin')->firstOrFail();
        Sanctum::actingAs($this->client);
    }

    private function setting(string $key, mixed $value): void
    {
        RestaurantSetting::query()->updateOrCreate(['key' => $key], ['value' => is_array($value) || is_bool($value) ? json_encode($value) : $value]);
    }

    public function test_tnd_quote_and_order_preserve_millimes_and_delivery_fee(): void
    {
        Product::query()->firstOrFail()->update(['price' => '1.005']);
        $this->setting('delivery_fee', '2.125');
        $payload = $this->checkoutPayload(['delivery_type' => 'delivery', 'delivery_postcode' => '3062',
            'delivery_address' => 'Adresse test Sfax', 'items' => [['product_id' => Product::query()->firstOrFail()->id, 'quantity' => 3]]]);
        $this->postJson('/api/orders', $payload)->assertCreated()->assertJsonPath('order.total', 5.14)
            ->assertJsonPath('order.currency', 'TND')->assertJsonPath('order.delivery_fee', 2.125);
        $this->assertSame('3.015', Order::query()->firstOrFail()->subtotal);
        $this->assertSame('1.005', Order::query()->firstOrFail()->items()->firstOrFail()->product_price);
    }

    public function test_retry_returns_original_order_even_after_prices_or_opening_change(): void
    {
        $payload = $this->checkoutPayload();
        $first = $this->postJson('/api/orders', $payload)->assertCreated()->json('order.id');
        $this->setting('ordering_enabled', false);
        Product::query()->firstOrFail()->update(['price' => '999.000']);
        $this->postJson('/api/orders', $payload)->assertOk()->assertJsonPath('order.id', $first);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_reusing_submission_key_for_changed_order_is_rejected(): void
    {
        $payload = $this->checkoutPayload();
        $this->postJson('/api/orders', $payload)->assertCreated();
        $payload['comments'] = 'different instructions';
        $this->postJson('/api/orders', $payload)->assertConflict();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_changed_prices_require_customer_to_confirm_a_new_quote(): void
    {
        $payload = $this->checkoutPayload();
        Product::query()->firstOrFail()->update(['price' => '42.125']);
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('quote_token');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_quotes_expire_and_cannot_be_used_by_another_customer(): void
    {
        $payload = $this->checkoutPayload();
        $this->travel(11)->minutes();
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('quote_token');
        $this->travelBack();
        $this->configureCheckout();
        $payload = $this->checkoutPayload();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('quote_token');
    }

    public function test_checkout_requires_a_signed_quote_and_contact_phone(): void
    {
        $payload = $this->checkoutPayload();
        $payload['quote_token'] = 'forged';
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('quote_token');
        unset($payload['contact_phone']);
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('contact_phone');
    }

    public function test_delivery_address_and_service_area_are_enforced(): void
    {
        $payload = $this->checkoutPayload(['delivery_type' => 'delivery', 'delivery_postcode' => '3062', 'delivery_address' => 'Sfax']);
        unset($payload['delivery_address']);
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('delivery_address');
        $payload['delivery_postcode'] = '9999';
        $this->postJson('/api/orders/quote', $payload)->assertUnprocessable()->assertJsonValidationErrors('delivery_postcode');
        $this->setting('delivery_enabled', false);
        $payload['delivery_postcode'] = '3062';
        $this->postJson('/api/orders/quote', $payload)->assertUnprocessable()->assertJsonValidationErrors('delivery_type');
    }

    public function test_minimum_unavailable_products_and_excessive_quantities_are_enforced(): void
    {
        $payload = $this->checkoutPayload();
        $this->setting('minimum_order_amount', '9999.000');
        $this->postJson('/api/orders/quote', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->setting('minimum_order_amount', '0.000');
        $payload['items'][0]['quantity'] = 100000000;
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        $payload['items'][0]['quantity'] = 1;
        $payload['items'][] = $payload['items'][0];
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $payload['items'] = [$payload['items'][0]];
        Product::query()->firstOrFail()->update(['is_available' => false]);
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_excessive_order_total_is_rejected(): void
    {
        $payload = $this->checkoutPayload();
        Product::query()->firstOrFail()->update(['price' => '99999.999']);
        $payload['items'][0]['quantity'] = 99;
        $this->postJson('/api/orders/quote', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_opening_hours_days_holidays_and_overnight_service_use_tunis_time(): void
    {
        $rules = app(RestaurantRules::class);
        $this->assertTrue($rules->isOpen());
        $this->travelTo(Carbon::parse('2026-11-10 22:30:00', 'Africa/Tunis'));
        $this->assertFalse($rules->isOpen());
        $this->setting('opens_at', '20:00');
        $this->setting('closes_at', '02:00');
        $this->setting('opening_days', [2]);
        $this->travelTo(Carbon::parse('2026-11-11 01:00:00', 'Africa/Tunis'));
        $this->assertTrue($rules->isOpen());
        $this->setting('closed_dates', ['2026-11-11']);
        $this->assertFalse($rules->isOpen());
        $this->postJson('/api/orders/quote', ['delivery_type' => 'takeaway',
            'items' => [['product_id' => Product::query()->firstOrFail()->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('restaurant');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->app['auth']->shouldUse('web');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'admin@gmail.com', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => 'admin@gmail.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_customer_order_submission_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/orders', [])->assertUnprocessable();
        }
        $this->postJson('/api/orders', [])->assertTooManyRequests();
    }

    public function test_only_admin_can_save_settings_and_empty_delivery_area_is_rejected(): void
    {
        $settings = app(RestaurantRules::class)->settings();
        $this->putJson('/api/admin/restaurant-settings', $settings)->assertForbidden();
        Sanctum::actingAs($this->admin);
        $settings['delivery_postcodes'] = [];
        $this->putJson('/api/admin/restaurant-settings', $settings)->assertUnprocessable()->assertJsonValidationErrors('delivery_postcodes');
        $settings['delivery_postcodes'] = ['3062', '3000'];
        $settings['delivery_fee'] = '3.125';
        $this->putJson('/api/admin/restaurant-settings', $settings)->assertOk()->assertJsonPath('settings.delivery_fee', '3.125');
        $this->getJson('/api/restaurant-settings')->assertOk()->assertJsonPath('settings.timezone', 'Africa/Tunis')->assertJsonPath('settings.currency', 'TND');
    }

    public function test_settings_seed_does_not_overwrite_operator_changes(): void
    {
        $this->setting('delivery_fee', '9.125');
        $this->seed(RestaurantSettingSeeder::class);
        $this->assertSame('9.125', RestaurantSetting::value('delivery_fee'));
    }

    public function test_processed_order_cannot_be_reversed_by_another_staff_action(): void
    {
        $order = Order::factory()->create(['user_id' => $this->client->id, 'status' => OrderStatus::PENDING]);
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/orders/'.$order->id.'/accept')->assertOk();
        $this->patchJson('/api/admin/orders/'.$order->id.'/reject')->assertUnprocessable();
        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
    }
}
