<?php

namespace App\Services;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly RestaurantRules $rules) {}

    private function calculate(array $payload): array
    {
        $settings = $this->rules->settings();
        $this->rules->assertCanOrder($payload, $settings);
        $products = Product::query()->whereIn('id', array_column($payload['items'], 'product_id'))->get()->keyBy('id');
        $items = [];
        $subtotal = 0;
        foreach ($payload['items'] as $item) {
            $product = $products->get((int) $item['product_id']);
            if (! $product || ! $product->is_available) {
                throw ValidationException::withMessages(['items' => 'Un ou plusieurs produits ne sont plus disponibles.']);
            }
            $price = Money::millimes($product->price);
            $line = $price * (int) $item['quantity'];
            $subtotal += $line;
            $items[] = ['product_id' => $product->id, 'product_name' => $product->name,
                'product_price' => Money::decimal($price), 'quantity' => (int) $item['quantity'], 'line_total' => Money::decimal($line)];
        }
        if ($subtotal < Money::millimes($settings['minimum_order_amount'])) {
            throw ValidationException::withMessages(['items' => 'Minimum de commande : '.$settings['minimum_order_amount'].' TND.']);
        }
        $fee = $payload['delivery_type'] === 'delivery' ? Money::millimes($settings['delivery_fee']) : 0;
        if ($subtotal + $fee > config('restaurant.max_total_millimes')) {
            throw ValidationException::withMessages(['items' => 'Le montant de la commande dépasse la limite autorisée.']);
        }

        return ['items' => $items, 'subtotal' => Money::decimal($subtotal), 'delivery_fee' => Money::decimal($fee),
            'total' => Money::decimal($subtotal + $fee), 'currency' => 'TND',
            'delivery_type' => $payload['delivery_type'],
            'delivery_postcode' => $payload['delivery_type'] === 'delivery' ? ($payload['delivery_postcode'] ?? null) : null];
    }

    private function fingerprint(array $quote): string
    {
        return hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR));
    }

    public function quote(User $user, array $payload): array
    {
        $quote = $this->calculate($payload);

        return [...$quote, 'quote_token' => Crypt::encryptString(json_encode([
            'user_id' => $user->id, 'fingerprint' => $this->fingerprint($quote), 'expires_at' => now()->addMinutes(10)->timestamp,
        ], JSON_THROW_ON_ERROR))];
    }

    public function create(User $user, array $payload): Order
    {
        $canonical = Arr::only($payload, ['delivery_type', 'delivery_postcode', 'delivery_address', 'contact_phone', 'comments', 'items']);
        if ($canonical['delivery_type'] === 'takeaway') {
            $canonical['delivery_address'] = null;
            $canonical['delivery_postcode'] = null;
        }
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $payload, $hash) {
            // Serialize submissions by customer; the unique database key is a second guard.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->orders()->where('idempotency_key', $payload['idempotency_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Cette référence a déjà été utilisée pour un autre panier.');

                return $existing->load(['items', 'user']);
            }
            $quote = $this->calculate($payload);
            try {
                $token = json_decode(Crypt::decryptString($payload['quote_token']), true, 512, JSON_THROW_ON_ERROR);
            } catch (DecryptException|\JsonException $exception) {
                throw ValidationException::withMessages(['quote_token' => 'Actualisez le total du panier avant de confirmer.']);
            }
            if (($token['user_id'] ?? null) !== $user->id || ($token['expires_at'] ?? 0) < now()->timestamp
                || ! hash_equals($token['fingerprint'] ?? '', $this->fingerprint($quote))) {
                throw ValidationException::withMessages(['quote_token' => 'Le panier ou les tarifs ont changé. Vérifiez le nouveau total avant de confirmer.']);
            }
            $delivery = DeliveryType::from($payload['delivery_type']);
            $order = Order::query()->create([
                'order_number' => 'MS-'.now('Africa/Tunis')->format('Ymd').'-'.Str::ulid(),
                'user_id' => $user->id, 'idempotency_key' => $payload['idempotency_key'], 'request_hash' => $hash,
                'currency' => 'TND', 'status' => OrderStatus::PENDING, 'delivery_type' => $delivery,
                'delivery_address' => $delivery === DeliveryType::DELIVERY ? ($payload['delivery_address'] ?? null) : null,
                'delivery_postcode' => $delivery === DeliveryType::DELIVERY ? $payload['delivery_postcode'] : null,
                'contact_phone' => $payload['contact_phone'], 'comments' => $payload['comments'] ?? null,
                ...Arr::only($quote, ['subtotal', 'delivery_fee', 'total']),
            ]);
            $order->items()->createMany($quote['items']);

            return $order->load(['items', 'user']);
        }, 3);
    }
}
