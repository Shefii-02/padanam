<?php

namespace App\Modules\Auth\DTOs;

use App\Core\DTO\Data;
use Illuminate\Http\Request;

final class DeviceData extends Data
{
    public function __construct(
        public readonly string $platform,
        public readonly ?string $fcm_token = null,
        public readonly ?int $app_build = null,
        public readonly ?string $device_name = null,
    ) {}

    public static function fromRequest(Request $request): static
    {
        return new self(
            platform: $request->input('device.platform', $request->input('platform', 'android')),
            fcm_token: $request->input('device.fcm_token', $request->input('fcm_token')),
            app_build: $request->integer('device.app_build') ?: null,
            device_name: $request->input('device.name'),
        );
    }

    public static function optional(Request $request): ?self
    {
        return $request->has('device') || $request->has('fcm_token') ? self::fromRequest($request) : null;
    }
}
