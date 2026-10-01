<?php

namespace App\Modules\System\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Core\Support\Settings;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    /** Flutter UpdateController: GET /public/app-version?platform=android&build=10 */
    public function appVersion(Request $r)
    {
        $d = $r->validate(['platform' => 'required|in:android,ios,web,windows,macos,linux', 'build' => 'required|integer']);
        $v = AppVersion::firstWhere('platform', $d['platform']);
        if (! $v) {
            return ApiResponse::ok(['update' => 'none']);
        }
        $update = $d['build'] < $v->min_supported_build ? 'immediate' : ($d['build'] < $v->latest_build ? 'flexible' : 'none');

        return ApiResponse::ok([
            'update' => $update,
            'latest_version' => $v->latest_version,
            'latest_build' => $v->latest_build,
            'min_supported_build' => $v->min_supported_build,
            'notes' => $v->notes,
            'store_url' => $v->store_url,
        ]);
    }

    public function versions()
    {
        return ApiResponse::ok(AppVersion::orderBy('id')->get());
    }

    public function saveVersion(Request $r, string $platform)
    {
        $d = $r->validate([
            'latest_version' => 'required|string|max:20', 'latest_build' => 'required|integer|min:1',
            'min_supported_build' => 'required|integer|min:1|lte:latest_build', 'notes' => 'nullable|string|max:1000', 'store_url' => 'nullable|url',
        ]);

        return ApiResponse::ok(AppVersion::updateOrCreate(['platform' => $platform], $d), 'Version saved');
    }

    public function settings()
    {
        return ApiResponse::ok(Settings::all());
    }

    public function saveSettings(Request $r)
    {
        $r->validate(['settings' => 'required|array']);
        foreach ($r->input('settings') as $key => $value) {
            if (array_key_exists($key, Settings::DEFAULTS)) {
                Settings::set($key, $value);
            }
        }

        return ApiResponse::ok(Settings::all(), 'Settings saved');
    }
}
