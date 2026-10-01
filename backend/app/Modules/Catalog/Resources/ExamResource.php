<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->exam_category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'full_name' => $this->full_name,
            'eligibility' => $this->eligibility,
            'pattern' => $this->pattern,
            'posts' => $this->posts,
            'next_exam_date' => $this->next_exam_date?->toDateString(),
            'days_left' => $this->next_exam_date ? max(0, (int) now()->startOfDay()->diffInDays($this->next_exam_date, false)) : null,
            'is_active' => $this->is_active,
        ];
    }
}
