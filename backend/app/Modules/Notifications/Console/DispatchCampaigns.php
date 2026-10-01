<?php

namespace App\Modules\Notifications\Console;

use App\Models\NotificationCampaign;
use App\Modules\Notifications\Services\CampaignService;
use Illuminate\Console\Command;

class DispatchCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch';

    protected $description = 'Send scheduled push campaigns that are due';

    public function handle(CampaignService $service): int
    {
        foreach (NotificationCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get() as $c) {
            try {
                $c = $service->send($c);
                $this->info("#{$c->id} sent to {$c->sent}");
            } catch (\Throwable $e) {
                $this->error("#{$c->id}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
