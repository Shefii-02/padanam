<?php

namespace App\Modules\Courses\Services;

use App\Core\Support\DomainException;
use App\Models\Article;
use App\Models\Content;
use App\Models\Course;
use App\Models\CourseFolder;
use App\Models\ExternalLink;
use App\Models\LiveClass;
use App\Models\Material;
use App\Models\Note;
use App\Models\Test;
use App\Models\Video;
use App\Modules\Courses\DTOs\ContentData;
use App\Modules\Courses\Events\ContentPublished;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ContentService
{
    public function list(Course $course, ?int $folderId, bool $outsideOnly = false)
    {
        return Content::query()->where('course_id', $course->id)
            ->when($outsideOnly, fn ($q) => $q->whereNull('folder_id'))
            ->when(! $outsideOnly && $folderId, fn ($q) => $q->where('folder_id', $folderId))
            ->with('contentable')->orderBy('sort')->get();
    }

    public function create(Course $course, ContentData $d): Content
    {
        if ($d->folder_id && ! CourseFolder::where('course_id', $course->id)->whereKey($d->folder_id)->exists()) {
            throw new DomainException('That folder belongs to another course.');
        }

        return DB::transaction(function () use ($course, $d) {
            $target = $this->makeTarget($d->type, $d->payload, null);
            $cols = $d->contentColumns() + ['access' => 'premium'];
            $cols['course_id'] = $course->id;
            $cols['created_by'] = auth()->id();
            $cols['sort'] ??= (int) Content::where('course_id', $course->id)->where('folder_id', $d->folder_id)->max('sort') + 1;
            $content = new Content($cols);
            $content->contentable()->associate($target);
            $content->save();

            if (! $content->publish_at || $content->publish_at->isPast()) {
                ContentPublished::dispatch($content);
            }

            return $content->load('contentable');
        });
    }

    public function update(Content $content, ContentData $d): Content
    {
        return DB::transaction(function () use ($content, $d) {
            if ($d->payload) {
                $this->makeTarget($content->type, $d->payload, $content->contentable);
            }
            $content->update(array_diff_key($d->contentColumns(), ['type' => true]));

            return $content->fresh('contentable');
        });
    }

    public function delete(Content $content): void
    {
        DB::transaction(function () use ($content) {
            // owned targets go with it; shared ones (tests, articles, live classes) stay
            if (in_array($content->type, ['video', 'pdf', 'note', 'link'], true)) {
                $content->contentable?->delete();
            }
            $content->delete();
        });
    }

    /** items: [{id, folder_id, sort}] – drag & drop across folders */
    public function reorder(Course $course, array $items): void
    {
        DB::transaction(function () use ($course, $items) {
            foreach ($items as $i) {
                Content::where('course_id', $course->id)->whereKey($i['id'])
                    ->update(['sort' => (int) $i['sort'], 'folder_id' => $i['folder_id'] ?? null]);
            }
        });
    }

    /** Creates or updates the typed record behind a content item. */
    private function makeTarget(string $type, array $p, ?Model $existing): Model
    {
        return match ($type) {
            'video' => $this->video($p, $existing),
            'pdf' => $this->material($p, $existing),
            'note' => $this->note($p, $existing),
            'link' => tap($existing ?? new ExternalLink, fn ($m) => $m->fill(array_filter(['url' => $p['url'] ?? null, 'label' => $p['note_title'] ?? null]))->save()),
            'test' => $existing ?? Test::findOrFail($p['ref_id'] ?? 0),
            'quiz' => $existing ?? Test::findOrFail($p['ref_id'] ?? 0),
            'article' => $existing ?? Article::findOrFail($p['ref_id'] ?? 0),
            'live' => $existing ?? LiveClass::findOrFail($p['ref_id'] ?? 0),
            default => throw new DomainException('Unknown content type.'),
        };
    }

    private function video(array $p, ?Model $v): Video
    {
        $v ??= new Video;
        $source = $p['source'] ?? $v->source ?? 'youtube';
        $data = ['source' => $source];
        if ($source === 'youtube') {
            $id = LiveClass::youtubeId($p['youtube_id'] ?? $p['url'] ?? null) ?? $v->youtube_id;
            if (! $id) {
                throw new DomainException('Paste a valid YouTube link (unlisted is fine).');
            }
            $data += ['youtube_id' => $id, 'url' => 'https://youtu.be/'.$id, 'thumbnail' => "https://i.ytimg.com/vi/$id/hqdefault.jpg"];
        } else {
            $data += array_filter(['s3_key' => $p['s3_key'] ?? null, 'hls_path' => $p['hls_path'] ?? null]);
            if (! ($data['hls_path'] ?? $v->hls_path) && ! ($data['s3_key'] ?? $v->s3_key)) {
                throw new DomainException('Add the AWS video path (HLS playlist or S3 key).');
            }
        }
        if (isset($p['duration_sec'])) {
            $data['duration_sec'] = (int) $p['duration_sec'];
        }
        $v->fill($data)->save();

        return $v;
    }

    private function material(array $p, ?Model $m): Material
    {
        $m ??= new Material;
        if (isset($p['file'])) {
            $file = $p['file'];
            $m->fill(['file_path' => $file->store('materials', 'public'), 'mime' => $file->getMimeType(), 'size_bytes' => $file->getSize()]);
        } elseif (! $m->exists) {
            throw new DomainException('Choose a PDF file to upload.');
        }
        if (isset($p['downloadable'])) {
            $m->downloadable = filter_var($p['downloadable'], FILTER_VALIDATE_BOOL);
        }
        $m->save();

        return $m;
    }

    private function note(array $p, ?Model $n): Note
    {
        $n ??= new Note(['body' => []]);
        $n->fill(array_filter([
            'title' => $p['note_title'] ?? null,
            'body' => $p['body'] ?? null,
            'tip' => $p['tip'] ?? null,
            'one_liners' => $p['one_liners'] ?? null,
        ], fn ($v) => $v !== null));
        if (! $n->body) {
            throw new DomainException('Write the note in at least one language.');
        }
        $n->save();

        return $n;
    }
}
