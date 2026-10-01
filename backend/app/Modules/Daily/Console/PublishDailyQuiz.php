<?php

namespace App\Modules\Daily\Console;

use App\Modules\Daily\Services\DailyQuizService;
use Illuminate\Console\Command;

class PublishDailyQuiz extends Command
{
    protected $signature = 'daily:publish {--date=}';

    protected $description = "Build and publish today's daily quizzes";

    public function handle(DailyQuizService $service): int
    {
        $n = $service->publishFor(\Illuminate\Support\Carbon::parse($this->option('date') ?: today()));
        $this->info("$n daily quizzes published");

        return self::SUCCESS;
    }
}
