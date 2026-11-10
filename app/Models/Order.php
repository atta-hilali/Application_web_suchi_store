<?php

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'currency', 'idempotency_key', 'request_hash', 'delivery_postcode', 'contact_phone',
        'user_id',
        'status',
        'delivery_type',
        'delivery_address',
        'comments',
        'subtotal',
        'delivery_fee',
        'total',
        'accepted_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_type' => DeliveryType::class,
            'subtotal' => 'decimal:3',
            'delivery_fee' => 'decimal:3',
            'total' => 'decimal:3',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
