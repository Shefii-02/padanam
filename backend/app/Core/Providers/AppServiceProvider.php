<?php

namespace App\Core\Providers;

use App\Core\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        JsonResource::withoutWrapping();
        // Log (don't crash) on N+1 lazy loading during development
        Model::preventLazyLoading(! app()->isProduction());
        Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
            logger()->warning('Lazy loaded '.get_class($model).'::'.$relation);
        });

        // short morph names (stored in contents.contentable_type, audit_logs.subject_type)
        Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'course' => \App\Models\Course::class,
            'batch' => \App\Models\Batch::class,
            'content' => \App\Models\Content::class,
            'video' => \App\Models\Video::class,
            'live_class' => \App\Models\LiveClass::class,
            'material' => \App\Models\Material::class,
            'note' => \App\Models\Note::class,
            'article' => \App\Models\Article::class,
            'test' => \App\Models\Test::class,
            'link' => \App\Models\ExternalLink::class,
            'order' => \App\Models\Order::class,
            'coupon' => \App\Models\Coupon::class,
            'question' => \App\Models\Question::class,
            'chat_room' => \App\Models\ChatRoom::class,
            'lead' => \App\Models\Lead::class,
        ]);

        // super_admin passes every permission check
        Gate::before(fn ($user) => $user->hasRole(Role::SuperAdmin->value) ? true : null);
    }
}
