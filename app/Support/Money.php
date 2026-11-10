<?php

namespace App\Support;

final class Money
{
    public static function millimes(string|int|float $amount): int
    {
        // Convert at the boundary; additions and multiplications use integer millimes.
        return (int) round((float) $amount * 1000, 0, PHP_ROUND_HALF_UP);
    }

    public static function decimal(int $millimes): string
    {
        return sprintf('%d.%03d', intdiv($millimes, 1000), $millimes % 1000);
    }
}
