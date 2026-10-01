<?php

namespace App\Modules\Courses\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Store card in the app. */
class CourseCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $batch = $this->batches->where('status', 'active')->sortBy('price')->first();
        $b = $batch ? (new BatchResource($batch))->resolve() : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category?->name,
            'course_type' => $this->course_type,
            'language' => $this->language,
            'thumbnail_url' => $this->thumbnail ? asset('storage/'.$this->thumbnail) : null,
            'short_description' => $this->short_description,
            'rating' => $this->rating,
            'students_count' => $this->students_count,
            'is_featured' => $this->is_featured,
            'price_text' => $b['price_text'] ?? null,
            'mrp_text' => $b['mrp_text'] ?? null,
            'discount_percent' => $b['discount_percent'] ?? 0,
            'is_free' => $b['is_free'] ?? false,
            'batches_count' => $this->batches->count(),
            'is_enrolled' => (bool) ($this->is_enrolled ?? false),
        ];
    }
}
