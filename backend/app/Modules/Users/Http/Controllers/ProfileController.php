<?php

namespace App\Modules\Users\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Modules\Users\DTOs\ProfileSetupData;
use App\Modules\Users\Http\Requests\ProfileSetupRequest;
use App\Modules\Users\Resources\UserResource;
use App\Modules\Users\Services\ProfileService;
use Illuminate\Http\Request;

/** App side: the student's own profile. */
class ProfileController extends Controller
{
    public function __construct(private ProfileService $profiles) {}

    public function show(Request $r)
    {
        return ApiResponse::ok(new UserResource($r->user()->load('profile', 'interests.category', 'roles')));
    }

    public function setup(ProfileSetupRequest $r)
    {
        $d = ProfileSetupData::fromRequest($r);
        $user = $this->profiles->completeSetup($r->user(), $d);
        $user->load('interests.category', 'interests.exam', 'profile', 'roles');
        $main = $user->interests->first();
        $exam = $main?->exam ?? \App\Models\Exam::where('exam_category_id', $main?->exam_category_id)->whereNotNull('next_exam_date')->orderBy('next_exam_date')->first();
        $days = $exam?->next_exam_date ? max(0, (int) now()->startOfDay()->diffInDays($exam->next_exam_date, false)) : null;
        $studyDays = count(array_filter($d->study_days));

        return ApiResponse::ok([
            'user' => (new \App\Modules\Auth\Resources\MeResource($user))->resolve(),
            'plan' => [
                'main_exam' => ['id' => $main?->category?->slug, 'name' => $main?->category?->name, 'post' => $main?->target_post, 'emoji' => $main?->category?->icon],
                'days_to_exam' => $days,
                'hours_per_week' => $d->study_hours * $studyDays,
                'topics_planned' => $days ? min(120, (int) ceil($days / 3)) : 40,
                'slot' => $d->study_slot,
            ],
        ], 'Your plan is ready');
    }

    public function update(Request $r)
    {
        $data = $r->validate([
            'name' => 'sometimes|string|min:2|max:80',
            'avatar' => 'sometimes|nullable|string|max:16',
            'district' => 'sometimes|nullable|string|max:60',
            'language' => 'sometimes|in:ml,en,both',
            'study_hours' => 'sometimes|integer|between:1,12',
            'study_slot' => 'sometimes|nullable|string|max:20',
            'reminder' => 'sometimes|boolean',
            'exams' => 'sometimes|array|max:3',
            'target_posts' => 'sometimes|array',
        ]);

        return ApiResponse::ok(new UserResource($this->profiles->update($r->user(), $data)), 'Saved');
    }

    public function photo(Request $r)
    {
        $r->validate(['photo' => 'required|image|max:3072']);
        $path = $r->file('photo')->store('avatars', 'public');
        $r->user()->update(['photo' => $path]);

        return ApiResponse::ok(['photo_url' => asset('storage/'.$path)], 'Photo updated');
    }
}
