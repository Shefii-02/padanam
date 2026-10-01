<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function dashboard(Request $r)
    {
        return ApiResponse::ok($this->reports->dashboard($r->user()));
    }

    public function revenue(Request $r)
    {
        abort_unless($r->user()->can('payments.view'), 403);

        return ApiResponse::ok($this->reports->revenue($r->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'group_by' => 'nullable|in:course,batch,month,gateway,coupon'])));
    }

    public function activity(Request $r)
    {
        return ApiResponse::ok($this->reports->activity($r->validate(['inactive_days' => 'nullable|integer|between:1,365'])));
    }

    public function performance(Request $r)
    {
        return ApiResponse::ok($this->reports->performance($r->validate(['course_id' => 'nullable|integer', 'from' => 'nullable|date', 'min_tests' => 'nullable|integer|min:1', 'limit' => 'nullable|integer|max:500'])));
    }

    public function export(Request $r, string $type)
    {
        abort_unless($r->user()->can('reports.export'), 403);
        $rows = match ($type) {
            'revenue' => $this->reports->revenue($r->all())['rows']->map(fn ($x) => [$x['label'], $x['orders'], $x['revenue']])->prepend(['Group', 'Orders', 'Revenue (₹)'])->all(),
            'performance' => collect($this->reports->performance($r->all()))->map(fn ($x) => [$x['rank'], $x['name'], $x['district'], $x['tests'], $x['avg_percent'], $x['accuracy']])->prepend(['Rank', 'Name', 'District', 'Tests', 'Avg %', 'Accuracy %'])->all(),
            default => abort(404),
        };
        \App\Core\Support\Audit::log('reports.export', null, ['type' => $type]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $type.'_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
