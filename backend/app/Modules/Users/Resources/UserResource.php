<?php

namespace App\Modules\Users\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'photo_url' => $this->photo ? asset('storage/'.$this->photo) : null,
            'initials' => $this->initials(),
            'gender' => $this->gender,
            'dob' => $this->dob?->toDateString(),
            'age' => $this->age(),
            'district' => $this->district,
            'state' => $this->state,
            'town' => $this->town,
            'pincode' => $this->pincode,
            'qualification' => $this->qualification,
            'language' => $this->language,
            'status' => $this->status,
            'is_new_user' => $this->is_new_user,
            'referral_code' => $this->referral_code,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'profile' => $this->whenLoaded('profile', fn () => $this->profile ? [
                'target_posts' => $this->profile->target_posts ?? (object) [],
                'level' => $this->profile->level,
                'aim' => $this->profile->aim,
                'attempt' => $this->profile->attempt,
                'study_hours' => $this->profile->study_hours,
                'study_days' => $this->profile->study_days,
                'study_slot' => $this->profile->study_slot,
                'reminder' => $this->profile->reminder,
            ] : null),
            'interests' => $this->whenLoaded('interests', fn () => $this->interests->map(fn ($i) => [
                'category_id' => $i->exam_category_id, 'exam_id' => $i->exam_id, 'target_post' => $i->target_post, 'source' => $i->source,
            ])),
            'staff' => $this->whenLoaded('staffProfile', fn () => $this->staffProfile ? [
                'designation' => $this->staffProfile->designation,
                'subjects' => $this->staffProfile->subjects,
                'is_teacher' => $this->staffProfile->is_teacher,
            ] : null),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
        ];
    }
}
