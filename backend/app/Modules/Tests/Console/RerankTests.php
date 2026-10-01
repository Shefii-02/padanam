<?php

namespace App\Modules\Tests\Console;

use App\Models\Test;
use App\Modules\Tests\Services\ScoringService;
use Illuminate\Console\Command;

class RerankTests extends Command
{
    protected $signature = 'tests:rerank {test?}';

    protected $description = 'Recalculate ranks and percentiles (tests with attempts in the last 2 days, or one test)';

    public function handle(ScoringService $scoring): int
    {
        $tests = $this->argument('test')
            ? Test::whereKey($this->argument('test'))->get()
            : Test::whereHas('attempts', fn ($a) => $a->where('submitted_at', '>=', now()->subDays(2)))->get();
        foreach ($tests as $t) {
            $this->line($t->title.': '.$scoring->rerank($t).' ranked');
        }

        return self::SUCCESS;
    }
}
