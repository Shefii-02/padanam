<?php

namespace App\Core\Support;

use Illuminate\Support\Str;

final class Code
{
    /** Human-friendly code without 0/O/1/I. */
    public static function make(int $length = 8, string $prefix = ''): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $prefix.$out;
    }

    public static function orderNo(): string
    {
        return 'PDN'.now()->format('ymd').strtoupper(Str::random(5));
    }

    public static function token(int $len = 32): string
    {
        return Str::random($len);
    }
}
