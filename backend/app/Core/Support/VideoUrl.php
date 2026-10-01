<?php

namespace App\Core\Support;

use App\Models\Video;
use Aws\CloudFront\UrlSigner;
use Illuminate\Support\Facades\Storage;

/** Playable source for the app player. YouTube (unlisted) by default; AWS signed HLS in rare cases. */
final class VideoUrl
{
    public static function payload(Video $v, int $ttlMinutes = 240): array
    {
        if ($v->source === 'youtube') {
            return ['kind' => 'youtube', 'youtube_id' => $v->youtube_id, 'url' => $v->url, 'duration_sec' => $v->duration_sec];
        }

        $path = $v->hls_path ?: $v->s3_key;
        $cf = config('services.aws_video');
        if (! empty($cf['cloudfront_domain']) && ! empty($cf['cloudfront_key_pair_id']) && is_readable((string) $cf['cloudfront_private_key'])) {
            $signer = new UrlSigner($cf['cloudfront_key_pair_id'], $cf['cloudfront_private_key']);
            $url = $signer->getSignedUrl('https://'.$cf['cloudfront_domain'].'/'.ltrim($path, '/'), now()->addMinutes($ttlMinutes)->timestamp);
        } else {
            $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes($ttlMinutes));
        }

        return ['kind' => str_ends_with($path, '.m3u8') ? 'hls' : 'mp4', 'url' => $url, 'expires_in' => $ttlMinutes * 60, 'duration_sec' => $v->duration_sec];
    }
}
