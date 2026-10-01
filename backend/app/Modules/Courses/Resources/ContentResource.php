<?php

namespace App\Modules\Courses\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Admin view of a content item (includes the typed details for editing). */
class ContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'folder_id' => $this->folder_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'access' => $this->access,
            'sort' => $this->sort,
            'publish_at' => $this->publish_at?->toIso8601String(),
            'unlock_after_content_id' => $this->unlock_after_content_id,
            'batch_ids' => $this->batch_ids,
            'meta' => $this->whenLoaded('contentable', fn () => self::meta($this->type, $this->contentable)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public static function meta(string $type, $m): array
    {
        if (! $m) {
            return [];
        }

        return match ($type) {
            'video' => ['source' => $m->source, 'youtube_id' => $m->youtube_id, 'url' => $m->url, 'duration_sec' => $m->duration_sec, 'thumbnail' => $m->thumbnail, 'has_aws' => (bool) ($m->hls_path || $m->s3_key)],
            'pdf' => ['size_bytes' => $m->size_bytes, 'pages' => $m->pages, 'downloadable' => $m->downloadable],
            'note' => ['languages' => array_keys($m->body ?? [])],
            'link' => ['url' => $m->url],
            'test', 'quiz' => ['test_id' => $m->id, 'questions' => $m->total_questions, 'duration_min' => $m->duration_min, 'marks' => $m->total_marks, 'mode' => $m->mode, 'status' => $m->status],
            'article' => ['article_id' => $m->id, 'reading_min' => $m->reading_min, 'status' => $m->status],
            'live' => ['live_class_id' => $m->id, 'starts_at' => $m->starts_at?->toIso8601String(), 'status' => $m->status, 'source' => $m->source],
            default => [],
        };
    }
}
