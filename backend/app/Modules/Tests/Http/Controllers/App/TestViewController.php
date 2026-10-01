<?php

namespace App\Modules\Tests\Http\Controllers\App;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Attempt;
use App\Models\Test;
use App\Modules\Tests\Services\TestViewService;
use Illuminate\Http\Request;

/** Screen-shaped endpoints for the Flutter test engine. */
class TestViewController extends Controller
{
    public function __construct(private TestViewService $view) {}

    public function series(Request $r)
    {
        return ApiResponse::ok($this->view->series($r->user(), $r->query('category'), $r->query('status', 'all'), $r->query('kind')));
    }

    public function instructions(Request $r, Test $test)
    {
        abort_unless($test->status === 'published', 404);

        return ApiResponse::ok($this->view->instructions($test, $r->user()));
    }

    public function summary(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);

        return ApiResponse::ok($this->view->summary($attempt));
    }

    public function analysis(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);
        abort_unless($attempt->test->resultVisible(), 403, 'Analysis opens with the result.');

        return ApiResponse::ok($this->view->analysis($attempt, $r->user()));
    }

    public function review(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);

        return ApiResponse::ok($this->view->review($attempt));
    }

    public function performance(Request $r)
    {
        return ApiResponse::ok($this->view->performance($r->user()));
    }

    private function own(Request $r, Attempt $attempt): void
    {
        abort_unless($attempt->user_id === $r->user()->id, 404);
    }
}
