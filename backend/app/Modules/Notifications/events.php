<?php

use App\Modules\Courses\Events\ContentPublished;
use App\Modules\Notifications\Listeners\NotifyNewContent;
use Illuminate\Support\Facades\Event;

Event::listen(ContentPublished::class, NotifyNewContent::class);
