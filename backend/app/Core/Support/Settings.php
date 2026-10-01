<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Key/value settings with cache (revenue share, invoice seller, chat defaults …). */
final class Settings
{
    public const DEFAULTS = [
        'revenue_share.enabled' => false,
        'revenue_share.percent' => 0,
        'revenue_share.partner_name' => 'Partner',
        'invoice.seller' => ['name' => 'Padanam Learning', 'gstin' => null, 'address' => 'Kerala, India', 'state_code' => '32'],
        'invoice.prefix' => 'INV-',
        'invoice.gst_percent' => 18,
        'invoice.prices_include_gst' => true,
        'live.alert_minutes_before' => 5,
        'chat.student_direct_to_student' => false,
        'whatsapp.templates' => [],
        'whatsapp.auto' => ['payment_link' => true, 'invoice' => true, 'admission' => false, 'swap' => true],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? (self::DEFAULTS[$key] ?? null));
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget('settings.all');
    }

    public static function all(): array
    {
        return Cache::rememberForever('settings.all', function () {
            $rows = DB::table('settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all();

            return $rows + self::DEFAULTS;
        });
    }
}
