<?php

namespace App\Modules\Users\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Row for the admin Students table (counts come from withCount / withAvg in the repository). */
class StudentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $days = $this->last_seen_at ? (int) $this->last_seen_at->diffInDays(now()) : null;

        return [
            'id' => $this->id,
            'name' => $this->name ?: 'Unnamed student',
            'phone' => $this->phone,
            'initials' => $this->initials(),
            'district' => $this->district,
            'interests' => $this->whenLoaded('interests', fn () => $this->interests->map(fn ($i) => $i->category?->name)->filter()->unique()->values()),
            'active_courses' => (int) ($this->active_enrollments_count ?? 0),
            'avg_score_percent' => $this->avg_score_percent !== null ? round((float) $this->avg_score_percent, 1) : null,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'is_active' => $days !== null && $days < 14,
            'inactive_days' => $days,
            'joined_at' => $this->created_at?->toDateString(),
        ];
    }
}
