<?php

namespace App\Modules\Users\Services;

use App\Models\ExamCategory;
use App\Models\User;
use App\Models\UserExamInterest;
use App\Modules\Users\DTOs\ProfileSetupData;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    public function completeSetup(User $user, ProfileSetupData $d): User
    {
        return DB::transaction(function () use ($user, $d) {
            $user->fill([
                'name' => $d->name,
                'avatar' => $d->avatar,
                'gender' => $d->gender,
                'dob' => $d->dob,
                'district' => $d->district,
                'state' => $d->state,
                'town' => $d->town,
                'pincode' => $d->pincode,
                'qualification' => $d->qualification,
                'language' => $d->language ?? $user->language,
                'is_new_user' => false,
                'profile_completed_at' => now(),
            ])->save();

            $user->profile()->updateOrCreate([], [
                'target_posts' => $d->target_posts,
                'level' => $d->level,
                'aim' => $d->aim,
                'attempt' => $d->attempt,
                'study_hours' => $d->study_hours,
                'study_days' => $d->study_days,
                'study_slot' => $d->study_slot,
                'reminder' => $d->reminder,
            ]);

            $this->syncInterests($user, $d->exams, $d->target_posts);

            return $user->fresh(['profile', 'interests.category', 'roles']);
        });
    }

    /** Exams picked in setup become interests (used for recommendations, leads and campaigns). */
    public function syncInterests(User $user, array $exams, array $posts = []): void
    {
        $cats = ExamCategory::query()
            ->whereIn('slug', array_filter($exams, 'is_string'))
            ->orWhereIn('id', array_filter($exams, 'is_numeric'))
            ->get();

        UserExamInterest::where('user_id', $user->id)->where('source', 'setup')->delete();
        foreach ($cats as $c) {
            UserExamInterest::updateOrCreate(
                ['user_id' => $user->id, 'exam_category_id' => $c->id, 'exam_id' => null],
                ['source' => 'setup', 'target_post' => $posts[$c->slug] ?? $posts[$c->id] ?? null]
            );
        }
    }

    public function update(User $user, array $data): User
    {
        $profileKeys = ['level', 'aim', 'attempt', 'study_hours', 'study_days', 'study_slot', 'reminder', 'target_posts'];
        $user->fill(array_diff_key($data, array_flip([...$profileKeys, 'exams'])))->save();
        if ($p = array_intersect_key($data, array_flip($profileKeys))) {
            $user->profile()->updateOrCreate([], $p);
        }
        if (isset($data['exams'])) {
            $this->syncInterests($user, $data['exams'], $data['target_posts'] ?? []);
        }

        return $user->fresh(['profile', 'interests.category', 'roles']);
    }
}
