<?php

namespace App\Modules\Users\Services;

use App\Core\Support\Audit;
use App\Core\Support\DomainException;
use App\Models\StaffProfile;
use App\Models\User;
use App\Modules\Users\DTOs\StaffData;
use App\Modules\Users\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAdminService
{
    public function __construct(private UserRepository $users) {}

    public function createStaff(StaffData $d): User
    {
        if (in_array($d->role, ['super_admin'], true) && ! auth()->user()->hasRole('super_admin')) {
            throw new DomainException('Only a super admin can create another super admin.', 403);
        }

        return DB::transaction(function () use ($d) {
            $user = User::create([
                'name' => $d->name,
                'email' => $d->email,
                'phone' => $d->phone,
                'password' => $d->password,
                'is_new_user' => false,
            ]);
            $user->syncRoles([$d->role]);
            StaffProfile::create([
                'user_id' => $user->id,
                'designation' => $d->designation,
                'subjects' => $d->subjects,
                'is_teacher' => $d->is_teacher ?? $d->role === 'teacher',
            ]);
            if ($d->course_ids) {
                $user->managedCourses()->syncWithPivotValues($d->course_ids, ['role' => $d->role === 'teacher' ? 'teacher' : 'manager']);
            }
            Audit::log('users.create', $user, ['role' => $d->role]);

            return $user->load('roles', 'staffProfile', 'managedCourses:id,title');
        });
    }

    public function updateStaff(User $user, StaffData $d): User
    {
        return DB::transaction(function () use ($user, $d) {
            $data = $d->toArray();
            $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'phone', 'password'])))->save();
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }
            $sp = array_intersect_key($data, array_flip(['designation', 'subjects', 'is_teacher']));
            if ($sp) {
                $user->staffProfile()->updateOrCreate([], $sp);
            }
            if (array_key_exists('course_ids', $data)) {
                $user->managedCourses()->syncWithPivotValues($data['course_ids'] ?? [], ['role' => $user->isTeacher() ? 'teacher' : 'manager']);
            }

            return $user->load('roles', 'staffProfile', 'managedCourses:id,title');
        });
    }

    public function setStatus(User $user, string $status): User
    {
        if ($user->id === auth()->id()) {
            throw new DomainException("You can't block yourself.");
        }
        if ($user->hasRole('super_admin') && ! auth()->user()->hasRole('super_admin')) {
            throw new DomainException("You can't block a super admin.", 403);
        }
        $user->update(['status' => $status]);
        Audit::log('users.'.($status === 'blocked' ? 'block' : 'unblock'), $user);

        return $user;
    }

    /** Streams a CSV (opens in Excel). Every export is audited – contact data is sensitive. */
    public function export(array $filters, array $fields): StreamedResponse
    {
        $allowed = [
            'name' => fn ($u) => $u->name,
            'phone' => fn ($u) => $u->phone,
            'email' => fn ($u) => $u->email,
            'district' => fn ($u) => $u->district,
            'interests' => fn ($u) => $u->interests->map(fn ($i) => $i->category?->name)->filter()->implode(', '),
            'courses' => fn ($u) => $u->enrollments->map(fn ($e) => $e->batch?->name)->filter()->implode(', '),
            'last_seen' => fn ($u) => $u->last_seen_at?->toDateTimeString(),
            'avg_score' => fn ($u) => $u->avg_score_percent !== null ? round((float) $u->avg_score_percent, 1) : '',
            'joined' => fn ($u) => $u->created_at?->toDateString(),
        ];
        $fields = array_values(array_intersect($fields ?: array_keys($allowed), array_keys($allowed)));
        Audit::log('users.export', null, ['filters' => $filters, 'fields' => $fields]);

        $query = $this->users->students()->with(['interests.category', 'enrollments' => fn ($q) => $q->active()->with('batch:id,name')]);
        $this->users->filtered($query, $filters);

        $name = 'students_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query, $fields, $allowed) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows Malayalam correctly
            fputcsv($out, array_map(fn ($f) => ucwords(str_replace('_', ' ', $f)), $fields));
            $query->chunkById(500, function ($rows) use ($out, $fields, $allowed) {
                foreach ($rows as $u) {
                    fputcsv($out, array_map(fn ($f) => $allowed[$f]($u), $fields));
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Student 360: enrollments, tests, payments, activity. */
    public function studentDetail(User $user): array
    {
        $user->load(['profile', 'interests.category', 'roles']);
        $attempts = $user->attempts()->with('test:id,title,total_marks')->whereNotNull('submitted_at')->latest('submitted_at')->limit(20)->get();

        return [
            'user' => $user,
            'enrollments' => $user->enrollments()->with('batch:id,name,course_id', 'course:id,title')->latest()->get(),
            'orders' => $user->orders()->with('batch:id,name')->latest()->limit(20)->get(),
            'attempts' => $attempts,
            'stats' => [
                'tests_taken' => $user->attempts()->whereNotNull('submitted_at')->count(),
                'avg_score_percent' => round((float) $attempts->avg(fn ($a) => $a->test?->total_marks ? $a->score / $a->test->total_marks * 100 : 0), 1),
                'best_rank' => $attempts->min('rank'),
                'watch_minutes_30d' => (int) DB::table('daily_user_stats')->where('user_id', $user->id)->where('date', '>=', now()->subDays(30))->sum('minutes'),
                'active_days_30d' => (int) DB::table('daily_user_stats')->where('user_id', $user->id)->where('date', '>=', now()->subDays(30))->where('active', true)->count(),
            ],
        ];
    }
}
