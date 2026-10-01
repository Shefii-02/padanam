<?php

namespace App\Modules\Courses\DTOs;

use App\Core\DTO\Data;
use Illuminate\Http\Request;

/**
 * One DTO for every content type. Type-specific fields:
 *  video : source (youtube|aws), url / youtube_id / s3_key, duration_sec
 *  pdf   : file (upload), downloadable
 *  note  : body {en, ml}, tip, one_liners
 *  link  : url
 *  test / article / live : ref_id (existing Test / Article / LiveClass)
 */
final class ContentData extends Data
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?int $folder_id = null,
        public readonly ?string $access = null,
        public readonly ?string $publish_at = null,
        public readonly ?int $unlock_after_content_id = null,
        public readonly ?array $batch_ids = null,
        public readonly ?int $sort = null,
        public readonly array $payload = [],
    ) {}

    public static function fromRequest(Request $r): static
    {
        $keys = ['type', 'title', 'description', 'folder_id', 'access', 'publish_at', 'unlock_after_content_id', 'batch_ids', 'sort'];
        $payload = $r->only(['source', 'url', 'youtube_id', 's3_key', 'hls_path', 'duration_sec', 'downloadable', 'body', 'tip', 'one_liners', 'ref_id', 'note_title']);
        if ($r->hasFile('file')) {
            $payload['file'] = $r->file('file');
        }

        return (new self(
            type: $r->input('type'),
            title: $r->input('title'),
            description: $r->input('description'),
            folder_id: $r->input('folder_id'),
            access: $r->input('access'),
            publish_at: $r->input('publish_at'),
            unlock_after_content_id: $r->input('unlock_after_content_id'),
            batch_ids: $r->input('batch_ids'),
            sort: $r->input('sort'),
            payload: $payload,
        ))->withProvided(array_keys($r->only($keys)));
    }

    public function contentColumns(): array
    {
        return array_diff_key($this->toArray(), array_flip(['payload']));
    }
}
