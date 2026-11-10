<?php

return [
    'currency' => 'TND',
    'timezone' => 'Africa/Tunis',
    'max_quantity' => 99,
    'max_items' => 50,
    'max_total_millimes' => 999999999,
    'defaults' => [
        'delivery_fee' => '0.000', 'minimum_order_amount' => '0.000',
        'ordering_enabled' => true, 'delivery_enabled' => false, 'delivery_postcodes' => [],
        'opening_days' => [1, 2, 3, 4, 5, 6, 7], 'opens_at' => '11:30', 'closes_at' => '22:30', 'closed_dates' => [],
    ],
];
