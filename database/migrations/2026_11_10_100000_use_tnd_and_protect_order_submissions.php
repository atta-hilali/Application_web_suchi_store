<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 12, 3)->change();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('product_price', 12, 3)->change();
            $table->decimal('line_total', 12, 3)->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 3)->change();
            $table->decimal('delivery_fee', 12, 3)->default(0)->change();
            $table->decimal('total', 12, 3)->change();
            $table->string('currency', 3)->default('TND');
            $table->uuid('idempotency_key')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->string('delivery_postcode', 4)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->unique(['user_id', 'idempotency_key']);
        });
        // Preserve historical order currencies rather than relabeling past payments.
        DB::table('orders')->update(['currency' => 'EUR']);
        DB::table('restaurant_settings')->updateOrInsert(['key' => 'currency'], ['value' => 'TND']);
    }

    public function down(): void
    {
        // Preserve three-decimal columns on rollback to avoid losing millimes.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropColumn(['currency', 'idempotency_key', 'request_hash', 'delivery_postcode', 'contact_phone']);
        });
    }
};
