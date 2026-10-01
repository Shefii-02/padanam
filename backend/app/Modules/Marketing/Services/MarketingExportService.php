<?php

namespace App\Modules\Marketing\Services;

use App\Core\Support\Audit;
use App\Models\Attempt;
use App\Models\Lead;
use App\Models\MarketingExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Marketing data export – one tab per audience type, each with its own filters.
 * Formats: standard CSV, WhatsApp broadcast list, Google Ads / Meta Ads customer-match upload.
 */
class MarketingExportService
{
    public const TYPES = ['students', 'buyers', 'non_buyers', 'expiring', 'inactive', 'test_takers', 'app_installs', 'leads'];

    public const FIELDS = [
        'students' => ['name', 'phone', 'email', 'district', 'gender', 'qualification', 'exams', 'courses', 'joined', 'last_seen'],
        'buyers' => ['name', 'phone', 'email', 'district', 'exams', 'courses', 'total_paid', 'last_purchase', 'last_seen'],
        'non_buyers' => ['name', 'phone', 'email', 'district', 'exams', 'joined', 'last_seen'],
        'expiring' => ['name', 'phone', 'district', 'course', 'batch', 'expires_at', 'last_seen'],
        'inactive' => ['name', 'phone', 'district', 'exams', 'courses', 'last_seen', 'days_inactive'],
        'test_takers' => ['name', 'phone', 'district', 'tests_taken', 'avg_percent', 'best_rank', 'last_test'],
        'app_installs' => ['name', 'phone', 'district', 'platform', 'app_build', 'last_active', 'purchased'],
        'leads' => ['name', 'phone', 'email', 'district', 'source', 'status', 'score', 'interested_in', 'created'],
    ];

    public const FORMATS = ['standard', 'whatsapp', 'google_ads', 'meta_ads'];

    public function count(string $type, array $f): int
    {
        // sub-query count so HAVING filters (avg %) and duplicates are handled
        return \Illuminate\Support\Facades\DB::query()->fromSub($this->query($type, $f)->toBase(), 'x')->distinct()->count('x.phone');
    }

    public function export(string $type, array $f, array $fields, string $format, int $by): StreamedResponse
    {
        $allowed = self::FIELDS[$type];
        $fields = array_values(array_intersect($fields ?: $allowed, $allowed));
        $query = $this->query($type, $f);
        $rows = $this->count($type, $f);
        MarketingExport::create(['type' => $type, 'filters' => $f, 'fields' => $fields, 'rows' => $rows, 'format' => $format, 'created_by' => $by]);
        Audit::log('marketing.export', null, ['type' => $type, 'format' => $format, 'rows' => $rows]);

        [$header, $map] = $this->formatter($type, $format, $fields, $f);
        $name = "padanam_{$type}_{$format}_".now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query, $header, $map) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            $seen = [];
            $query->chunkById(500, function ($rows) use ($out, $map, &$seen) {
                foreach ($rows as $row) {
                    $phone = $row->phone;
                    if (! $phone || isset($seen[$phone])) {
                        continue;   // one row per number
                    }
                    $seen[$phone] = true;
                    fputcsv($out, $map($row));
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Builder of User (or Lead for "leads") with the filters of that tab. */
    public function query(string $type, array $f): Builder
    {
        if ($type === 'leads') {
            return Lead::query()->with('course:id,title', 'category:id,name')
                ->when(($f['status'] ?? null), fn ($q, $v) => $q->whereIn('status', (array) $v), fn ($q) => $q->where('status', '!=', 'converted'))
                ->when($f['source'] ?? null, fn ($q, $v) => $q->whereIn('source', (array) $v))
                ->when($f['min_score'] ?? null, fn ($q, $v) => $q->where('score', '>=', (int) $v))
                ->when($f['course_ids'] ?? null, fn ($q, $v) => $q->whereIn('interested_course_id', (array) $v))
                ->when($f['category_ids'] ?? null, fn ($q, $v) => $q->whereIn('interested_category_id', (array) $v))
                ->when($f['districts'] ?? null, fn ($q, $v) => $q->whereIn('district', (array) $v))
                ->when($f['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
                ->when($f['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()));
        }

        $q = User::query()->role('student')->where('status', 'active')->whereNotNull('phone')
            ->with(['interests.category:id,name', 'enrollments' => fn ($e) => $e->with('batch:id,name,course_id', 'course:id,title')]);

        // common filters for every user-based tab
        $q->when($f['category_ids'] ?? null, fn ($w, $v) => $w->whereHas('interests', fn ($i) => $i->whereIn('exam_category_id', (array) $v)))
            ->when($f['districts'] ?? null, fn ($w, $v) => $w->whereIn('district', (array) $v))
            ->when($f['gender'] ?? null, fn ($w, $v) => $w->where('gender', $v))
            ->when($f['language'] ?? null, fn ($w, $v) => $w->where('language', $v))
            ->when($f['joined_from'] ?? null, fn ($w, $v) => $w->where('created_at', '>=', $v))
            ->when($f['joined_to'] ?? null, fn ($w, $v) => $w->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($f['profile_complete'] ?? null, fn ($w, $v) => $v === '1' ? $w->whereNotNull('profile_completed_at') : $w->whereNull('profile_completed_at'));

        $courseIds = (array) ($f['course_ids'] ?? []);
        $batchIds = (array) ($f['batch_ids'] ?? []);

        switch ($type) {
            case 'students':
                $q->when(($f['active_days'] ?? null), fn ($w, $v) => $w->where('last_seen_at', '>=', now()->subDays((int) $v)))
                    ->when(isset($f['paid']) && $f['paid'] !== '', fn ($w) => $f['paid'] === '1'
                        ? $w->whereHas('orders', fn ($o) => $o->where('status', 'paid')->where('total', '>', 0))
                        : $w->whereDoesntHave('orders', fn ($o) => $o->where('status', 'paid')->where('total', '>', 0)));
                break;

            case 'buyers':
                $q->whereHas('orders', fn ($o) => $o->where('status', 'paid')->where('total', '>', 0)
                    ->when($f['from'] ?? null, fn ($x, $v) => $x->where('paid_at', '>=', $v))
                    ->when($f['to'] ?? null, fn ($x, $v) => $x->where('paid_at', '<=', Carbon::parse($v)->endOfDay()))
                    ->when($courseIds, fn ($x) => $x->whereHas('batch', fn ($b) => $b->whereIn('course_id', $courseIds)))
                    ->when($batchIds, fn ($x) => $x->whereIn('batch_id', $batchIds)))
                    ->when($f['enrollment_status'] ?? null, fn ($w, $v) => $w->whereHas('enrollments', fn ($e) => $e->where('status', $v)
                        ->when($courseIds, fn ($x) => $x->whereIn('course_id', $courseIds))))
                    ->when($f['not_course_ids'] ?? null, fn ($w, $v) => $w->whereDoesntHave('enrollments', fn ($e) => $e->whereIn('course_id', (array) $v)->where('status', 'active')))
                    ->withSum(['orders as total_paid' => fn ($o) => $o->where('status', 'paid')], 'total')
                    ->withMax(['orders as last_purchase' => fn ($o) => $o->where('status', 'paid')], 'paid_at');
                break;

            case 'non_buyers':
                $q->whereDoesntHave('orders', fn ($o) => $o->where('status', 'paid')->where('total', '>', 0))
                    ->when($f['active_days'] ?? null, fn ($w, $v) => $w->where('last_seen_at', '>=', now()->subDays((int) $v)))
                    ->when($f['viewed_course_ids'] ?? null, fn ($w, $v) => $w->whereExists(fn ($x) => $x->from('leads')->whereColumn('leads.phone', 'users.phone')->whereIn('interested_course_id', (array) $v)));
                break;

            case 'expiring':
                $days = max(1, (int) ($f['within_days'] ?? 15));
                $q->whereHas('enrollments', fn ($e) => $e->where('status', 'active')->whereBetween('expires_at', [now(), now()->addDays($days)])
                    ->when($courseIds, fn ($x) => $x->whereIn('course_id', $courseIds)));
                break;

            case 'inactive':
                $days = max(1, (int) ($f['days'] ?? 14));
                $q->where(fn ($w) => $w->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays($days)))
                    ->when(($f['paid_only'] ?? null) === '1', fn ($w) => $w->whereHas('enrollments', fn ($e) => $e->where('status', 'active')->where('source', 'purchase')
                        ->when($courseIds, fn ($x) => $x->whereIn('course_id', $courseIds))));
                break;

            case 'test_takers':
                $att = fn ($a) => $a->whereNotNull('submitted_at')
                    ->when($f['test_ids'] ?? null, fn ($x, $v) => $x->whereIn('test_id', (array) $v))
                    ->when($courseIds, fn ($x) => $x->whereHas('test', fn ($t) => $t->whereIn('course_id', $courseIds)))
                    ->when($f['from'] ?? null, fn ($x, $v) => $x->where('submitted_at', '>=', $v))
                    ->when($f['to'] ?? null, fn ($x, $v) => $x->where('submitted_at', '<=', Carbon::parse($v)->endOfDay()));
                $q->whereHas('attempts', $att)
                    ->withCount(['attempts as tests_taken' => $att])
                    ->withMin(['attempts as best_rank' => $att], 'rank')
                    ->withMax(['attempts as last_test' => $att], 'submitted_at')
                    ->addSelect(['avg_percent' => Attempt::query()->selectRaw('ROUND(AVG(attempts.score / NULLIF(tests.total_marks,0) * 100),1)')
                        ->join('tests', 'tests.id', '=', 'attempts.test_id')->whereColumn('attempts.user_id', 'users.id')->whereNotNull('attempts.submitted_at')]);
                if (($f['min_percent'] ?? '') !== '' || ($f['max_percent'] ?? '') !== '') {
                    $q->having('avg_percent', '>=', (float) ($f['min_percent'] ?? 0))->having('avg_percent', '<=', (float) ($f['max_percent'] ?? 100));
                }
                break;

            case 'app_installs':
                $q->whereHas('devices', fn ($d) => $d->when($f['platform'] ?? null, fn ($x, $v) => $x->where('platform', $v))
                    ->when($f['active_days'] ?? null, fn ($x, $v) => $x->where('last_active_at', '>=', now()->subDays((int) $v))))
                    ->when(($f['no_purchase'] ?? null) === '1', fn ($w) => $w->whereDoesntHave('orders', fn ($o) => $o->where('status', 'paid')->where('total', '>', 0)))
                    ->with(['devices' => fn ($d) => $d->latest('last_active_at')])
                    ->withExists(['orders as purchased' => fn ($o) => $o->where('status', 'paid')->where('total', '>', 0)]);
                break;
        }

        return $q;
    }

    private function formatter(string $type, string $format, array $fields, array $f): array
    {
        $split = function (?string $name): array {
            $parts = preg_split('/\s+/', trim((string) $name), 2);

            return [$parts[0] ?? '', $parts[1] ?? ''];
        };
        $intl = fn ($p) => '91'.substr(preg_replace('/\D/', '', (string) $p), -10);

        if ($format === 'whatsapp') {
            return [['Name', 'Phone'], fn ($r) => [$r->name ?: 'Student', '+'.$intl($r->phone)]];
        }
        if ($format === 'google_ads') {
            return [['Phone', 'First Name', 'Last Name', 'Country'], fn ($r) => ['+'.$intl($r->phone), ...$split($r->name), 'IN']];
        }
        if ($format === 'meta_ads') {
            return [['phone', 'fn', 'ln', 'ct', 'st', 'country'], fn ($r) => [$intl($r->phone), ...array_map('strtolower', $split($r->name)), strtolower((string) $r->district), 'kerala', 'in']];
        }

        $courseIds = array_map('intval', (array) ($f['course_ids'] ?? []));
        $expiring = fn ($r) => $r->enrollments->where('status', 'active')->filter(fn ($e) => $e->expires_at && (! $courseIds || in_array($e->course_id, $courseIds, true)))->sortBy('expires_at')->first();
        $get = [
            'name' => fn ($r) => $r->name, 'phone' => fn ($r) => $r->phone, 'email' => fn ($r) => $r->email, 'district' => fn ($r) => $r->district,
            'gender' => fn ($r) => $r->gender, 'qualification' => fn ($r) => $r->qualification,
            'exams' => fn ($r) => $r->interests?->map(fn ($i) => $i->category?->name)->filter()->unique()->implode(', '),
            'courses' => fn ($r) => $r->enrollments?->where('status', 'active')->map(fn ($e) => $e->course?->title)->filter()->unique()->implode(', '),
            'joined' => fn ($r) => $r->created_at?->toDateString(), 'last_seen' => fn ($r) => $r->last_seen_at?->toDateTimeString(),
            'total_paid' => fn ($r) => isset($r->total_paid) ? round($r->total_paid / 100, 2) : '', 'last_purchase' => fn ($r) => $r->last_purchase,
            'course' => fn ($r) => $expiring($r)?->course?->title, 'batch' => fn ($r) => $expiring($r)?->batch?->name,
            'expires_at' => fn ($r) => $expiring($r)?->expires_at?->toDateString(),
            'days_inactive' => fn ($r) => $r->last_seen_at ? (int) $r->last_seen_at->diffInDays(now()) : 'never',
            'tests_taken' => fn ($r) => $r->tests_taken ?? '', 'avg_percent' => fn ($r) => $r->avg_percent ?? '', 'best_rank' => fn ($r) => $r->best_rank ?? '',
            'last_test' => fn ($r) => $r->last_test ?? '',
            'platform' => fn ($r) => $r->devices?->first()?->platform, 'app_build' => fn ($r) => $r->devices?->first()?->app_build,
            'last_active' => fn ($r) => $r->devices?->first()?->last_active_at, 'purchased' => fn ($r) => ! empty($r->purchased) ? 'yes' : 'no',
            // leads
            'source' => fn ($r) => $r->source, 'status' => fn ($r) => $r->status, 'score' => fn ($r) => $r->score,
            'interested_in' => fn ($r) => $r->course?->title ?? $r->category?->name, 'created' => fn ($r) => $r->created_at?->toDateString(),
        ];

        return [array_map(fn ($x) => ucwords(str_replace('_', ' ', $x)), $fields), fn ($r) => array_map(fn ($x) => $get[$x]($r), $fields)];
    }
}
