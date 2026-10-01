<?php

namespace App\Core\Support;

/** All money is stored as integer paise. */
final class Money
{
    public static function toPaise(float|int|string|null $rupees): int
    {
        return (int) round(((float) $rupees) * 100);
    }

    public static function toRupees(?int $paise): float
    {
        return round(($paise ?? 0) / 100, 2);
    }

    public static function format(?int $paise): string
    {
        $r = self::toRupees($paise);
        $whole = (int) floor($r);
        $s = (string) $whole;
        if (strlen($s) > 3) {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $s = $rest.','.$last3;
        }
        $paise = (int) round(($r - $whole) * 100);

        return '₹'.$s.($paise ? '.'.str_pad((string) $paise, 2, '0', STR_PAD_LEFT) : '');
    }
}
