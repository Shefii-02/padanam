<?php

namespace App\Modules\Articles\Services;

use App\Models\Article;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Str;

class ArticleService
{
    public function save(array $d, ?Article $a = null): Article
    {
        if (isset($d['body'])) {
            $d['reading_min'] = max(1, (int) ceil(str_word_count(strip_tags($d['body'])) / 200));
        }
        if (($d['status'] ?? null) === 'published' && ! ($a?->published_at)) {
            $d['published_at'] ??= now();
        }
        if (! $a) {
            $base = Str::slug($d['title']) ?: Str::lower(Str::random(6));
            $slug = $base;
            for ($i = 2; Article::withTrashed()->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            $d['slug'] = $slug;
            $d['author_id'] = auth()->id();

            return Article::create($d);
        }
        $a->update($d);

        return $a->fresh();
    }

    /** Premium articles are for students with any active paid/manual enrollment. */
    public function canRead(Article $a, ?User $u): bool
    {
        return $a->access === 'free' || ($u && ($u->isPanelUser() || Enrollment::where('user_id', $u->id)->active()->exists()));
    }

    public function visible()
    {
        return Article::query()->where('status', '!=', 'draft')->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn ($w) => $w->where('status', 'published')->orWhere(fn ($x) => $x->where('status', 'scheduled')->where('published_at', '<=', now())));
    }
}
