<?php

namespace App\Modules\Courses\Resources;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Admin view of a course. */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cheapest = $this->relationLoaded('batches') ? $this->batches->where('status', 'active')->sortBy('price')->first() : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'exam_category_id' => $this->exam_category_id,
            'exam_id' => $this->exam_id,
            'course_type' => $this->course_type,
            'language' => $this->language,
            'thumbnail_url' => $this->thumbnail ? asset('storage/'.$this->thumbnail) : null,
            'intro_video_url' => $this->intro_video_url,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'what_you_get' => $this->what_you_get,
            'level' => $this->level,
            'features' => $this->features,
            'feature_labels' => Course::FEATURES,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'students_count' => $this->students_count,
            'rating' => $this->rating,
            'price_from' => $cheapest ? (new BatchResource($cheapest))->resolve()['price_text'] : null,
            'batches_count' => $this->whenCounted('batches'),
            'batches' => BatchResource::collection($this->whenLoaded('batches')),
            'staff' => $this->whenLoaded('staff', fn () => $this->staff->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->pivot->role])),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
