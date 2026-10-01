<?php

namespace App\Modules\Courses\DTOs;

use App\Core\DTO\Data;
use Illuminate\Http\Request;

final class CourseData extends Data
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?int $exam_category_id = null,
        public readonly ?int $exam_id = null,
        public readonly ?string $course_type = null,
        public readonly ?string $language = null,
        public readonly ?string $thumbnail = null,
        public readonly ?string $intro_video_url = null,
        public readonly ?string $short_description = null,
        public readonly ?string $description = null,
        public readonly ?array $what_you_get = null,
        public readonly ?string $level = null,
        public readonly ?array $features = null,
        public readonly ?bool $is_featured = null,
        public readonly ?int $sort = null,
        // default batch values when creating
        public readonly ?float $price = null,
        public readonly ?float $mrp = null,
        public readonly ?array $staff = null,      // [{user_id, role}]
    ) {}

    public static function fromRequest(Request $r): static
    {
        $keys = ['title', 'exam_category_id', 'exam_id', 'course_type', 'language', 'intro_video_url', 'short_description',
            'description', 'what_you_get', 'level', 'features', 'is_featured', 'sort', 'price', 'mrp', 'staff'];

        return (new self(
            title: $r->input('title'),
            exam_category_id: $r->input('exam_category_id'),
            exam_id: $r->input('exam_id'),
            course_type: $r->input('course_type'),
            language: $r->input('language'),
            thumbnail: $r->hasFile('thumbnail') ? $r->file('thumbnail')->store('courses', 'public') : null,
            intro_video_url: $r->input('intro_video_url'),
            short_description: $r->input('short_description'),
            description: $r->input('description'),
            what_you_get: $r->input('what_you_get'),
            level: $r->input('level'),
            features: $r->input('features'),
            is_featured: $r->has('is_featured') ? $r->boolean('is_featured') : null,
            sort: $r->input('sort'),
            price: $r->has('price') ? (float) $r->input('price') : null,
            mrp: $r->has('mrp') ? (float) $r->input('mrp') : null,
            staff: $r->input('staff'),
        ))->withProvided(array_merge(array_keys($r->only($keys)), $r->hasFile('thumbnail') ? ['thumbnail'] : []));
    }

    /** Only columns of the courses table. */
    public function courseColumns(): array
    {
        return array_diff_key($this->toArray(), array_flip(['price', 'mrp', 'staff']));
    }
}
