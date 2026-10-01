<?php

namespace App\Modules\Reports\Services;

use App\Core\Support\Money;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** Admin home. Teachers see only their courses (no money). */
    public function dashboard(User $viewer): array
    {
        $courseIds = Course::query()->visibleTo($viewer)->pluck('id');
        $money = $viewer->can('payments.view');
        $paid = fn () => Order::where('status', 'paid')->whereHas('batch', fn ($b) => $b->whereIn('course_id', $courseIds));

        $out = [
            'students' => [
                'total' => Enrollment::whereIn('course_id', $courseIds)->active()->distinct()->count('user_id'),
                'new_today' => User::role('student')->whereDate('created_at', today())->count(),
                'active_today' => User::role('student')->where('last_seen_at', '>=', today())->count(),
                'active_7d' => User::role('student')->where('last_seen_at', '>=', now()->subDays(7))->count(),
            ],
            'live_today' => LiveClass::whereIn('course_id', $courseIds)->whereDate('starts_at', today())->with('batch:id,name', 'teacher:id,name')
                ->orderBy('starts_at')->get(['id', 'title', 'starts_at', 'status', 'batch_id', 'teacher_id'])
                ->map(fn ($l) => ['id' => $l->id, 'title' => $l->title, 'time' => $l->starts_at->format('g:i A'), 'status' => $l->status, 'batch' => $l->batch?->name, 'teacher' => $l->teacher?->name]),
            'tests_today' => Attempt::whereDate('submitted_at', today())->count(),
            'open_doubts' => DB::table('doubts')->whereIn('course_id', $courseIds)->whereNull('answered_at')->whereNull('deleted_at')->count(),
            'signups_30d' => $this->series(User::role('student'), 'created_at', 30, 'count'),
            'top_courses' => Course::whereIn('id', $courseIds)->withCount(['enrollments' => fn ($e) => $e->active()])->orderByDesc('enrollments_count')->limit(5)
                ->get(['id', 'title'])->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'students' => $c->enrollments_count]),
        ];
        if ($money) {
            $out['revenue'] = [
                'today' => Money::format((int) $paid()->whereDate('paid_at', today())->sum('total')),
                'month' => Money::format((int) $paid()->where('paid_at', '>=', now()->startOfMonth())->sum('total')),
                'last_month' => Money::format((int) $paid()->whereBetween('paid_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])->sum('total')),
                'orders_today' => $paid()->whereDate('paid_at', today())->count(),
                'series_30d' => $this->series($paid(), 'paid_at', 30, 'sum', 'total'),
            ];
            $out['expiring_7d'] = Enrollment::whereIn('course_id', $courseIds)->active()->whereBetween('expires_at', [now(), now()->addDays(7)])->count();
        }

        return $out;
    }

    /** group_by: course | batch | month | gateway | coupon */
    public function revenue(array $f): array
    {
        $from = Carbon::parse($f['from'] ?? now()->startOfYear());
        $to = Carbon::parse($f['to'] ?? now())->endOfDay();
        $q = DB::table('orders')->join('batches', 'batches.id', '=', 'orders.batch_id')->join('courses', 'courses.id', '=', 'batches.course_id')
            ->leftJoin('coupons', 'coupons.id', '=', 'orders.coupon_id')
            ->where('orders.status', 'paid')->whereBetween('orders.paid_at', [$from, $to]);

        [$key, $label] = match ($f['group_by'] ?? 'course') {
            'batch' => ['batches.id', DB::raw("CONCAT(courses.title,' – ',batches.name) as label")],
            'month' => [DB::raw("DATE_FORMAT(orders.paid_at,'%Y-%m')"), DB::raw("DATE_FORMAT(orders.paid_at,'%Y-%m') as label")],
            'gateway' => ['orders.gateway', DB::raw('orders.gateway as label')],
            'coupon' => ['coupons.code', DB::raw("COALESCE(coupons.code,'No coupon') as label")],
            default => ['courses.id', DB::raw('courses.title as label')],
        };
        $rows = $q->groupBy($key)->select($label)->selectRaw('COUNT(*) orders, SUM(orders.total) revenue, SUM(orders.discount) discount')->orderByDesc('revenue')->get();
        $refunds = (int) DB::table('refunds')->join('payments', 'payments.id', '=', 'refunds.payment_id')->where('refunds.status', 'processed')->whereBetween('refunds.created_at', [$from, $to])->sum('refunds.amount');

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'rows' => $rows->map(fn ($r) => ['label' => $r->label, 'orders' => (int) $r->orders, 'revenue' => Money::toRupees((int) $r->revenue), 'revenue_text' => Money::format((int) $r->revenue), 'discount_text' => Money::format((int) $r->discount)]),
            'total' => Money::format((int) $rows->sum('revenue')),
            'refunds' => Money::format($refunds),
            'net' => Money::format((int) $rows->sum('revenue') - $refunds),
        ];
    }

    /** Active / inactive monitoring. */
    public function activity(array $f): array
    {
        $days = (int) ($f['inactive_days'] ?? 14);
        $students = User::role('student');
        $dau = DB::table('daily_user_stats')->where('date', '>=', now()->subDays(30))->where('active', true)
            ->groupBy('date')->orderBy('date')->selectRaw('date, count(*) users, SUM(minutes) minutes')->get();

        return [
            'total' => (clone $students)->count(),
            'active' => (clone $students)->where('last_seen_at', '>=', now()->subDays($days))->count(),
            'inactive' => (clone $students)->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays($days)))->count(),
            'paid_inactive' => (clone $students)->whereHas('enrollments', fn ($e) => $e->active()->where('source', 'purchase'))
                ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays($days)))->count(),
            'daily' => $dau->map(fn ($d) => ['date' => $d->date, 'users' => (int) $d->users, 'minutes' => (int) $d->minutes]),
            'by_district' => DB::table('users')->whereNotNull('district')->where('last_seen_at', '>=', now()->subDays($days))
                ->groupBy('district')->selectRaw('district, count(*) c')->orderByDesc('c')->limit(14)->pluck('c', 'district'),
        ];
    }

    /** Ranking of students by performance (avg % in tests) within a course/category. */
    public function performance(array $f): array
    {
        $q = DB::table('attempts')->join('tests', 'tests.id', '=', 'attempts.test_id')->join('users', 'users.id', '=', 'attempts.user_id')
            ->whereNotNull('attempts.submitted_at')->where('tests.total_marks', '>', 0)
            ->when($f['course_id'] ?? null, fn ($w, $v) => $w->where('tests.course_id', $v))
            ->when($f['from'] ?? null, fn ($w, $v) => $w->where('attempts.submitted_at', '>=', $v))
            ->groupBy('users.id', 'users.name', 'users.district')
            ->selectRaw('users.id, users.name, users.district, COUNT(*) tests, ROUND(AVG(attempts.score / tests.total_marks * 100),1) avg_percent, ROUND(AVG(attempts.correct/(NULLIF(attempts.correct+attempts.wrong,0))*100),1) accuracy')
            ->havingRaw('COUNT(*) >= ?', [(int) ($f['min_tests'] ?? 1)])
            ->orderByDesc('avg_percent')->limit(min(500, (int) ($f['limit'] ?? 100)));

        return $q->get()->values()->map(fn ($r, $i) => ['rank' => $i + 1] + (array) $r)->all();
    }

    private function series($query, string $col, int $days, string $agg, string $sumCol = 'id'): array
    {
        $rows = (clone $query)->where($col, '>=', today()->subDays($days - 1))
            ->selectRaw("DATE($col) d, ".($agg === 'sum' ? "SUM($sumCol)" : 'COUNT(*)').' v')->groupBy('d')->pluck('v', 'd');

        return collect(range($days - 1, 0))->map(function ($i) use ($rows, $agg) {
            $d = today()->subDays($i)->toDateString();
            $v = (int) ($rows[$d] ?? 0);

            return ['date' => $d, 'value' => $agg === 'sum' ? Money::toRupees($v) : $v];
        })->all();
    }
}
