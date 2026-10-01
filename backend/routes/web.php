<?php

use App\Models\ChatRoom;
use App\Models\Course;
use Illuminate\Support\Facades\Route;

/*
| Deep links. When the app is installed, Android App Links / iOS Universal Links open it directly
| (verified by the two /.well-known files). Otherwise these small pages show what it is + store buttons.
| Serve this app on the DEEP_LINK_HOST domain (e.g. https://padanam.app).
*/

Route::get('.well-known/assetlinks.json', fn () => response()->json([[
    'relation' => ['delegate_permission/common.handle_all_urls'],
    'target' => ['namespace' => 'android_app', 'package_name' => config('app.android_package'), 'sha256_cert_fingerprints' => array_values(config('app.android_sha256'))],
]]));

Route::get('.well-known/apple-app-site-association', fn () => response()->json([
    'applinks' => ['apps' => [], 'details' => [['appID' => config('app.ios_app_id'), 'paths' => ['/j/*', '/c/*', '/a/*', '/paid/*']]]],
])->header('Content-Type', 'application/json'));

$page = function (string $title, string $subtitle, string $emoji, string $appPath) {
    $play = e(config('app.play_store_url'));
    $ios = e(config('app.app_store_url'));
    $scheme = 'padanam://'.ltrim($appPath, '/');

    return response(<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} · Padanam</title><meta property="og:title" content="{$title}"><meta property="og:description" content="{$subtitle}">
<style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#F4F7F6;color:#0f172a;display:grid;place-items:center;min-height:100vh}
.card{background:#fff;border-radius:20px;padding:32px 24px;max-width:380px;width:88%;text-align:center;box-shadow:0 10px 30px rgba(15,118,110,.12)}
.e{font-size:56px}h1{font-size:22px;margin:12px 0 6px}p{color:#475569;margin:0 0 22px}
a.b{display:block;padding:14px;border-radius:12px;text-decoration:none;font-weight:600;margin-top:10px}
.p{background:#0F766E;color:#fff}.s{background:#E6F4F1;color:#0F766E}</style></head>
<body><div class="card"><div class="e">{$emoji}</div><h1>{$title}</h1><p>{$subtitle}</p>
<a class="b p" href="{$scheme}">Open in Padanam app</a>
<a class="b s" href="{$play}">Get it on Google Play</a><a class="b s" href="{$ios}">Download on the App Store</a></div></body></html>
HTML);
};

Route::get('j/{code}', function (string $code) use ($page) {
    $room = ChatRoom::where('invite_code', $code)->where('invite_enabled', true)->first();

    return $room
        ? $page(e($room->name), 'Group chat · '.$room->members_count.' members. Join from the Padanam app.', $room->avatar ?: '💬', 'j/'.$code)
        : $page('Link expired', 'This invite link is no longer active. Ask the admin for a new one.', '🔗', '');
});

Route::get('c/{slug}', function (string $slug) use ($page) {
    $c = Course::published()->where('slug', $slug)->firstOrFail();

    return $page(e($c->title), e($c->short_description ?? 'Learn on Padanam'), '🎓', 'c/'.$slug);
});

Route::get('a/{slug}', fn (string $slug) => $page('Read on Padanam', 'Open this article in the Padanam app.', '📰', 'a/'.$slug));

Route::get('paid/{order}', fn (string $order) => $page('Payment received?', 'Open the Padanam app – your course unlocks as soon as the payment is confirmed.', '✅', 'paid/'.$order));
