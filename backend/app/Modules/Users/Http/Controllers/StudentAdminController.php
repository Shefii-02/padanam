<?php

namespace App\Modules\Users\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\User;
use App\Modules\Users\Repositories\UserRepository;
use App\Modules\Users\Resources\StudentListResource;
use App\Modules\Users\Resources\UserResource;
use App\Modules\Users\Services\UserAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAdminController extends Controller
{
    public function __construct(private UserRepository $users, private UserAdminService $service) {}

    public function index(Request $r)
    {
        $page = $this->users->paginate($r->all(), ['interests.category:id,name'], $this->users->students());

        return ApiResponse::ok(StudentListResource::collection($page));
    }

    public function summary()
    {
        $students = User::role('student');

        return ApiResponse::ok([
            'total' => (clone $students)->count(),
            'active_7d' => (clone $students)->where('last_seen_at', '>=', now()->subDays(7))->count(),
            'inactive_14d' => (clone $students)->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(14)))->count(),
            'never_purchased' => (clone $students)->whereDoesntHave('orders', fn ($o) => $o->where('status', 'paid'))->count(),
            'new_this_week' => (clone $students)->where('created_at', '>=', now()->subWeek())->count(),
            'districts' => DB::table('users')->whereNotNull('district')->groupBy('district')->orderByDesc(DB::raw('count(*)'))->limit(14)->pluck(DB::raw('count(*) as c'), 'district'),
        ]);
    }

    public function show(User $user)
    {
        $d = $this->service->studentDetail($user);
        $d['user'] = (new UserResource($d['user']))->resolve();

        return ApiResponse::ok($d);
    }

    public function block(User $user)
    {
        return ApiResponse::ok(new UserResource($this->service->setStatus($user, 'blocked')), 'Student blocked');
    }

    public function unblock(User $user)
    {
        return ApiResponse::ok(new UserResource($this->service->setStatus($user, 'active')), 'Student unblocked');
    }

    public function export(Request $r)
    {
        return $this->service->export($r->except('fields'), (array) $r->input('fields', []));
    }
}
