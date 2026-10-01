<?php

namespace App\Modules\Security\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Core\Support\Audit;
use App\Models\OtpLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin → Security: OTP logs, login activity, audit trail. */
class SecurityController extends Controller
{
    public function otpLogs(Request $r)
    {
        $q = $this->otpQuery($r)->with('user:id,name')->latest('id');
        $page = $q->paginate(min(100, (int) $r->query('per_page', 25)));
        $since = now()->subDay();
        $day = OtpLog::where('created_at', '>=', $since);
        $sent = (clone $day)->whereIn('status', ['sent', 'verified', 'wrong_code', 'expired'])->count();
        $verified = (clone $day)->where('status', 'verified')->count();

        return ApiResponse::paginated($page->through(fn (OtpLog $l) => [
            'id' => $l->id, 'phone' => $l->phone, 'user' => $l->user?->only('id', 'name'), 'channel' => $l->channel, 'status' => $l->status,
            'attempts' => $l->attempts, 'ip' => $l->ip, 'platform' => $l->platform, 'user_agent' => $l->user_agent, 'error' => $l->error,
            'verified_at' => $l->verified_at, 'created_at' => $l->created_at,
        ]), ['summary' => [
            'sent_24h' => $sent, 'verified_24h' => $verified, 'success_rate' => $sent ? round($verified * 100 / $sent, 1) : null,
            'failed_24h' => (clone $day)->where('status', 'failed')->count(),
            'blocked_24h' => (clone $day)->whereIn('status', ['rate_limited', 'blocked'])->count(),
            'by_channel' => (clone $day)->selectRaw('channel, count(*) c')->groupBy('channel')->pluck('c', 'channel'),
            // numbers asking many OTPs – possible abuse
            'top_phones' => OtpLog::where('created_at', '>=', $since)->selectRaw('phone, count(*) c')->groupBy('phone')->having('c', '>=', 5)->orderByDesc('c')->limit(5)->get(),
        ]]);
    }

    public function exportOtpLogs(Request $r): StreamedResponse
    {
        Audit::log('security.export_otp_logs', null, $r->query());
        $q = $this->otpQuery($r)->latest('id');

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'Phone', 'Channel', 'Status', 'Attempts', 'IP', 'Platform', 'Error', 'Verified at']);
            $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $l) {
                    fputcsv($out, [$l->created_at, $l->phone, $l->channel, $l->status, $l->attempts, $l->ip, $l->platform, $l->error, $l->verified_at]);
                }
            });
            fclose($out);
        }, 'otp_logs_'.now()->format('Y-m-d_His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function logins(Request $r)
    {
        $q = DB::table('login_activities as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->select('l.*', 'u.name', 'u.phone', 'u.email')
            ->when($r->query('search'), fn ($w, $v) => $w->where(fn ($x) => $x->where('u.name', 'like', "%$v%")->orWhere('u.phone', 'like', "%$v%")->orWhere('u.email', 'like', "%$v%")))
            ->when($r->query('platform'), fn ($w, $v) => $w->where('l.platform', $v))
            ->when($r->query('staff') === '1', fn ($w) => $w->whereNotNull('u.email')->whereExists(fn ($e) => $e->from('model_has_roles as m')->join('roles as ro', 'ro.id', '=', 'm.role_id')
                ->whereColumn('m.model_id', 'u.id')->whereNotIn('ro.name', ['student'])))
            ->when($r->query('from'), fn ($w, $v) => $w->where('l.at', '>=', $v))
            ->when($r->query('to'), fn ($w, $v) => $w->where('l.at', '<=', Carbon::parse($v)->endOfDay()))
            ->orderByDesc('l.id');

        return ApiResponse::paginated($q->paginate(min(100, (int) $r->query('per_page', 25))));
    }

    public function audit(Request $r)
    {
        $q = DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->select('a.*', 'u.name as user_name')
            ->when($r->query('action'), fn ($w, $v) => $w->where('a.action', 'like', "$v%"))
            ->when($r->query('user_id'), fn ($w, $v) => $w->where('a.user_id', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->where(fn ($x) => $x->where('a.action', 'like', "%$v%")->orWhere('u.name', 'like', "%$v%")))
            ->when($r->query('from'), fn ($w, $v) => $w->where('a.created_at', '>=', $v))
            ->when($r->query('to'), fn ($w, $v) => $w->where('a.created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->orderByDesc('a.id');
        $page = $q->paginate(min(100, (int) $r->query('per_page', 25)));

        return ApiResponse::paginated($page->through(fn ($a) => [
            'id' => $a->id, 'action' => $a->action, 'user' => $a->user_name, 'subject' => $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : null,
            'meta' => $a->meta ? json_decode($a->meta, true) : null, 'ip' => $a->ip, 'created_at' => $a->created_at,
        ]), ['actions' => DB::table('audit_logs')->distinct()->orderBy('action')->limit(200)->pluck('action')]);
    }

    private function otpQuery(Request $r)
    {
        return OtpLog::query()
            ->when($r->query('search'), fn ($w, $v) => $w->where('phone', 'like', "%$v%"))
            ->when($r->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($r->query('channel'), fn ($w, $v) => $w->where('channel', $v))
            ->when($r->query('from'), fn ($w, $v) => $w->where('created_at', '>=', $v))
            ->when($r->query('to'), fn ($w, $v) => $w->where('created_at', '<=', Carbon::parse($v)->endOfDay()));
    }
}
