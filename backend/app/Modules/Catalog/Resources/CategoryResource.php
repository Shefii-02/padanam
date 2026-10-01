<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort' => $this->sort,
            'is_active' => $this->is_active,
            'courses_count' => $this->courses_count ?? null,
            'children' => self::collection($this->whenLoaded('children')),
            'exams' => ExamResource::collection($this->whenLoaded('exams')),
        ];
    }
}
