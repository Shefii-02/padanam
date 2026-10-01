<?php

namespace App\Modules\AppFeed\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Modules\AppFeed\Services\AppFeedService;
use Illuminate\Http\Request;

class AppFeedController extends Controller
{
    public function __construct(private AppFeedService $feed) {}

    public function config()
    {
        return ApiResponse::ok($this->feed->config());
    }

    public function setupOptions()
    {
        return ApiResponse::ok($this->feed->setupOptions());
    }

    public function home(Request $r)
    {
        return ApiResponse::ok($this->feed->home($r->user()));
    }

    public function profile(Request $r)
    {
        return ApiResponse::ok($this->feed->profilePage($r->user()));
    }

    public function exams(Request $r)
    {
        return ApiResponse::ok($this->feed->exams($r->user()));
    }

    public function examHub(Request $r, string $slug)
    {
        return ApiResponse::ok($this->feed->examHub($slug, $r->user()));
    }
}
