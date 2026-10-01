<?php

namespace App\Modules\Courses\Events;

use App\Models\Content;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired when students should hear about new content (Notifications module listens). */
class ContentPublished
{
    use Dispatchable;

    public function __construct(public Content $content) {}
}
