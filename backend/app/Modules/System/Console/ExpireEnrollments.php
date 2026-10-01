<?php

namespace App\Modules\System\Console;

use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use Illuminate\Console\Command;

class ExpireEnrollments extends Command
{
    protected $signature = 'enrollments:expire';

    protected $description = 'Mark ended enrollments as expired and remove them from batch chat groups';

    public function handle(): int
    {
        $expired = Enrollment::where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())->get();
        foreach ($expired as $e) {
            $e->update(['status' => 'expired']);
            $roomIds = ChatRoom::where('batch_id', $e->batch_id)->where('type', 'batch_group')->pluck('id');
            ChatMember::whereIn('room_id', $roomIds)->where('user_id', $e->user_id)->where('role', 'member')->update(['left_at' => now()]);
        }
        $this->info($expired->count().' enrollments expired');

        return self::SUCCESS;
    }
}
