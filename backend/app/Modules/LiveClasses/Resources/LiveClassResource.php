<?php

namespace App\Modules\LiveClasses\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'course' => $this->whenLoaded('course', fn () => $this->course?->title),
            'batch_id' => $this->batch_id,
            'batch' => $this->whenLoaded('batch', fn () => $this->batch?->name),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher ? ['id' => $this->teacher->id, 'name' => $this->teacher->name] : null),
            'folder_id' => $this->folder_id,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->starts_at?->copy()->addMinutes($this->duration_min)->toIso8601String(),
            'duration_min' => $this->duration_min,
            'source' => $this->source,
            'meet_url' => $this->meet_url,
            'stream_url' => $this->stream_url,
            'youtube_id' => $this->youtube_id,
            'status' => $this->status,
            'save_recording' => $this->save_recording,
            'recording_content_id' => $this->recording_content_id,
            'attendance_count' => $this->whenCounted('attendance'),
            'starts_in_min' => $this->status === 'scheduled' ? (int) now()->diffInMinutes($this->starts_at, false) : null,
        ];
    }
}
