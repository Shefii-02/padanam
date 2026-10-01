<?php

namespace App\Modules\System\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Rolls yesterday's activity_events into daily_user_stats (active/inactive monitoring). */
class DailyStats extends Command
{
    protected $signature = 'stats:daily {--date=}';

    protected $description = 'Aggregate daily user activity';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->subDay()->toDateString();
        $rows = DB::table('activity_events')->whereDate('at', $date)->whereNotNull('user_id')
            ->selectRaw("user_id,
                SUM(CASE WHEN event='watch_minutes' THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(properties,'$.minutes')) AS UNSIGNED) ELSE 0 END) minutes,
                SUM(event='video_play') videos, SUM(event='test_submit') tests, SUM(event='quiz_submit') quiz")
            ->groupBy('user_id')->get();

        foreach ($rows as $r) {
            DB::table('daily_user_stats')->updateOrInsert(['date' => $date, 'user_id' => $r->user_id], [
                'minutes' => (int) $r->minutes, 'videos' => (int) $r->videos, 'tests' => (int) $r->tests, 'quiz' => (int) $r->quiz, 'active' => true,
            ]);
        }
        $this->info(count($rows)." users active on $date");

        return self::SUCCESS;
    }
}
