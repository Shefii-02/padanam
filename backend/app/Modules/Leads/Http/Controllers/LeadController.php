<?php

namespace App\Modules\Leads\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Core\Support\Audit;
use App\Core\Support\Phone;
use App\Models\Lead;
use App\Modules\Leads\Services\LeadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(private LeadService $leads) {}

    /** App: POST /app/events {event, properties} – demo_play, offer_click, course_view, free_test_start, app_open, video_play, watch_minutes … */
    public function track(Request $r)
    {
        $d = $r->validate(['event' => 'required|string|max:60', 'properties' => 'nullable|array']);
        $this->leads->track($r->user(), $d['event'], $d['properties'] ?? [], $r->header('X-Platform'));

        return ApiResponse::ok(null);
    }

    public function index(Request $r)
    {
        $q = $this->filtered($r)->with('assignee:id,name', 'course:id,title', 'category:id,name')
            ->orderByRaw("FIELD(status,'new','contacted','converted','lost')")->orderByDesc('score')->paginate(50);
        $base = $this->filtered($r);

        return ApiResponse::ok(collect($q->items())->map(fn ($l) => [
            'id' => $l->id, 'name' => $l->name, 'phone' => $l->phone, 'district' => $l->district, 'source' => $l->source,
            'course' => $l->course?->title, 'category' => $l->category?->name, 'status' => $l->status, 'score' => $l->score,
            'assigned_to' => $l->assignee?->name, 'notes' => $l->notes, 'last_contacted_at' => $l->last_contacted_at?->toIso8601String(),
            'created_at' => $l->created_at->toIso8601String(),
            'whatsapp_url' => 'https://wa.me/91'.$l->phone,
        ]), 'OK', 200, [
            'pagination' => ['page' => $q->currentPage(), 'total' => $q->total(), 'last_page' => $q->lastPage()],
            'summary' => (clone $base)->reorder()->groupBy('status')->selectRaw('status, count(*) c')->pluck('c', 'status'),
            'by_source' => (clone $base)->reorder()->groupBy('source')->selectRaw('source, count(*) c')->pluck('c', 'source'),
        ]);
    }

    public function show(Lead $lead)
    {
        return ApiResponse::ok($lead->load('assignee:id,name', 'course:id,title', 'category:id,name', 'activities.author:id,name'));
    }

    public function store(Request $r)
    {
        $r->merge(['phone' => Phone::normalize($r->input('phone')) ?? $r->input('phone')]);
        $d = $r->validate(['name' => 'nullable|string|max:80', 'phone' => 'required|regex:/^[6-9]\d{9}$/', 'district' => 'nullable|string|max:60',
            'interested_course_id' => 'nullable|integer|exists:courses,id', 'interested_category_id' => 'nullable|integer|exists:exam_categories,id',
            'source' => 'nullable|in:manual,whatsapp,website', 'notes' => 'nullable|string|max:1000']);
        $lead = $this->leads->capture($d['phone'], $d['name'] ?? null, $d['source'] ?? 'manual', $d['interested_course_id'] ?? null, $d['interested_category_id'] ?? null);
        $lead->update(array_filter(['district' => $d['district'] ?? null, 'notes' => $d['notes'] ?? null]));

        return ApiResponse::created($lead, 'Lead added');
    }

    public function update(Request $r, Lead $lead)
    {
        $d = $r->validate(['status' => 'sometimes|in:new,contacted,converted,lost', 'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
            'notes' => 'sometimes|nullable|string|max:2000', 'name' => 'sometimes|string|max:80']);

        return ApiResponse::ok($this->leads->update($lead, $d, $r->user()), 'Saved');
    }

    public function activity(Request $r, Lead $lead)
    {
        $d = $r->validate(['type' => 'required|in:call,whatsapp,note,payment_link', 'note' => 'nullable|string|max:1000']);

        return ApiResponse::created($this->leads->log($lead, $d['type'], $d['note'] ?? null, $r->user()), 'Logged');
    }

    public function bulk(Request $r)
    {
        $d = $r->validate(['ids' => 'required|array|max:1000', 'status' => 'nullable|in:new,contacted,converted,lost', 'assigned_to' => 'nullable|integer|exists:users,id']);
        $n = Lead::whereIn('id', $d['ids'])->update(array_filter(['status' => $d['status'] ?? null, 'assigned_to' => $d['assigned_to'] ?? null]));

        return ApiResponse::ok(['updated' => $n], "$n leads updated");
    }

    /** Marketing export (contacts). Audited. */
    public function export(Request $r)
    {
        $rows = $this->filtered($r)->with('course:id,title', 'category:id,name');
        Audit::log('leads.export', null, $r->all());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Phone', 'District', 'Source', 'Interested course', 'Exam', 'Status', 'Score', 'Created']);
            $rows->chunkById(1000, function ($chunk) use ($out) {
                foreach ($chunk as $l) {
                    fputcsv($out, [$l->name, $l->phone, $l->district, $l->source, $l->course?->title, $l->category?->name, $l->status, $l->score, $l->created_at->toDateString()]);
                }
            });
            fclose($out);
        }, 'leads_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $r): Builder
    {
        return Lead::query()
            ->when($r->query('status'), fn ($w, $v) => $w->whereIn('status', (array) $v))
            ->when($r->query('source'), fn ($w, $v) => $w->whereIn('source', (array) $v))
            ->when($r->query('course_id'), fn ($w, $v) => $w->where('interested_course_id', $v))
            ->when($r->query('category_id'), fn ($w, $v) => $w->where('interested_category_id', $v))
            ->when($r->query('assigned_to'), fn ($w, $v) => $w->where('assigned_to', $v === 'me' ? $r->user()->id : $v))
            ->when($r->query('district'), fn ($w, $v) => $w->where('district', $v))
            ->when($r->query('min_score'), fn ($w, $v) => $w->where('score', '>=', $v))
            ->when($r->query('from'), fn ($w, $v) => $w->where('created_at', '>=', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->where(fn ($x) => $x->where('name', 'like', "%$v%")->orWhere('phone', 'like', "%$v%")));
    }
}
