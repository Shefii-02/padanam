<?php

namespace App\Modules\Articles\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Article;
use App\Modules\Articles\Services\ArticleService;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function __construct(private ArticleService $articles) {}

    // ---------- public / app ----------
    public function index(Request $r)
    {
        $page = $this->articles->visible()->with('category:id,name,slug', 'author:id,name')
            ->when($r->query('category'), fn ($w, $v) => $w->whereHas('category', fn ($c) => $c->where('slug', $v)))
            ->when($r->query('tag'), fn ($w, $v) => $w->whereJsonContains('tags', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->where('title', 'like', "%$v%"))
            ->latest('published_at')->paginate(20);

        return ApiResponse::ok(collect($page->items())->map(fn ($a) => $this->card($a)), 'OK', 200,
            ['pagination' => ['page' => $page->currentPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function show(string $slug)
    {
        $a = $this->articles->visible()->where('slug', $slug)->with('category:id,name,slug', 'author:id,name')->firstOrFail();
        $user = auth('api')->user();
        $a->increment('views');
        $can = $this->articles->canRead($a, $user);

        return ApiResponse::ok($this->card($a) + [
            'body' => $can ? $a->body : mb_substr(strip_tags($a->body), 0, 400).'…',
            'locked' => ! $can,
            'share_url' => rtrim(config('app.deep_link_host'), '/').'/a/'.$a->slug,
        ]);
    }

    // ---------- admin ----------
    public function adminIndex(Request $r)
    {
        return ApiResponse::ok(Article::with('category:id,name', 'author:id,name')
            ->when($r->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->where('title', 'like', "%$v%"))
            ->latest('id')->paginate(20)->through(fn ($a) => $this->card($a) + ['status' => $a->status]));
    }

    public function adminShow(Article $article)
    {
        return ApiResponse::ok($article);
    }

    public function store(Request $r)
    {
        return ApiResponse::created($this->articles->save($this->rules($r, true)), 'Article saved');
    }

    public function update(Request $r, Article $article)
    {
        return ApiResponse::ok($this->articles->save($this->rules($r, false), $article), 'Article saved');
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return ApiResponse::ok(null, 'Article deleted');
    }

    private function card(Article $a): array
    {
        return [
            'id' => $a->id, 'slug' => $a->slug, 'title' => $a->title,
            'cover_url' => $a->cover ? (str_starts_with($a->cover, 'http') ? $a->cover : asset('storage/'.$a->cover)) : null,
            'category' => $a->category?->name, 'tags' => $a->tags ?? [], 'reading_min' => $a->reading_min, 'access' => $a->access,
            'views' => $a->views, 'author' => $a->author?->name, 'published_at' => $a->published_at?->toIso8601String(),
            'excerpt' => mb_strimwidth(trim(strip_tags($a->body)), 0, 160, '…'),
        ];
    }

    private function rules(Request $r, bool $c): array
    {
        $d = $r->validate([
            'title' => [$c ? 'required' : 'sometimes', 'string', 'max:200'], 'body' => [$c ? 'required' : 'sometimes', 'string'],
            'cover' => 'nullable|image|max:4096', 'exam_category_id' => 'nullable|integer|exists:exam_categories,id',
            'tags' => 'nullable|array', 'tags.*' => 'string|max:40', 'access' => 'nullable|in:free,premium',
            'status' => 'nullable|in:draft,scheduled,published', 'published_at' => 'nullable|date|required_if:status,scheduled',
        ]);
        if ($r->hasFile('cover')) {
            $d['cover'] = $r->file('cover')->store('articles', 'public');
        }

        return $d;
    }
}
