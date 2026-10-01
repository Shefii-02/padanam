<?php

namespace App\Modules\LiveClasses\Console;

use App\Core\Support\Settings;
use App\Models\LiveClass;
use App\Modules\LiveClasses\Services\LiveClassService;
use App\Modules\Notifications\Services\Notifier;
use Illuminate\Console\Command;

/** Runs every minute: rings students (class_alert channel) N minutes before a class. */
class SendClassAlerts extends Command
{
    protected $signature = 'live:send-alerts';

    protected $description = 'Send the class-alert ring before scheduled live classes';

    public function handle(LiveClassService $live, Notifier $notifier): int
    {
        $minutes = (int) Settings::get('live.alert_minutes_before', 5);
        $classes = LiveClass::where('status', 'scheduled')->whereNull('alert_sent_at')
            ->whereBetween('starts_at', [now(), now()->addMinutes($minutes)->addSeconds(59)])
            ->with('teacher:id,name', 'batch:id,name')->get();

        foreach ($classes as $lc) {
            // claim first so two schedulers never double-ring
            if (! LiveClass::whereKey($lc->id)->whereNull('alert_sent_at')->update(['alert_sent_at' => now()])) {
                continue;
            }
            $res = $notifier->send($live->audience($lc), 'live.alert', [
                'title' => $lc->title, 'minutes' => max(1, (int) ceil(now()->diffInMinutes($lc->starts_at))),
                'teacher' => $lc->teacher?->name ?? 'Padanam', 'batch' => $lc->batch?->name, 'id' => $lc->id,
            ], ['live_class_id' => $lc->id, 'course_id' => $lc->course_id, 'type' => 'class_alert', 'starts_at' => $lc->starts_at->toIso8601String(), 'teacher' => $lc->teacher?->name ?? '', 'batch' => $lc->batch?->name ?? '']);
            $this->info("Alert for #{$lc->id} → ".($res['recipients'] ?? 0).' students');
        }

        return self::SUCCESS;
    }
}
