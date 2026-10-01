<?php

namespace Database\Seeders;

use App\Models\AppVersion;
use App\Models\ChatPolicy;
use App\Models\NotificationChannel;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/** Things every install needs: notification channels, templates, chat rules, app versions. */
class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['class_alert', 'Class alerts', 'Rings 5 minutes before your live class', 'max', 'class_ring', true],
            ['live_now', 'Live now', 'When a teacher starts a live class', 'high', null, true],
            ['course_update', 'Course updates', 'New videos, notes and tests in your courses', 'default', null, true],
            ['test_result', 'Test results', 'Scores and ranks', 'default', null, true],
            ['payment', 'Payments', 'Receipts and invoices', 'high', null, false],
            ['chat', 'Chat', 'Messages from groups and teachers', 'high', null, true],
            ['announcement', 'Announcements', 'Important news from Padanam', 'default', null, false],
            ['offer', 'Offers', 'Discounts and new course launches', 'low', null, true],
            ['reminder', 'Study reminders', 'Daily study plan and quiz reminders', 'default', null, true],
        ];
        foreach ($channels as [$key, $name, $desc, $imp, $sound, $toggle]) {
            NotificationChannel::updateOrCreate(['key' => $key], ['name' => $name, 'description' => $desc, 'importance' => $imp, 'sound' => $sound, 'user_can_disable' => $toggle]);
        }

        $templates = [
            ['live.alert', 'class_alert', '⏰ {title} starts in {minutes} min', '{teacher} · {batch}. Tap to join.', '/live/{id}'],
            ['live.now', 'live_now', '🔴 Live now: {title}', '{teacher} is live. Join the class.', '/live/{id}'],
            ['content.new', 'course_update', 'New in {course}', '{type}: {title}', '/course/{course_id}'],
            ['payment.success', 'payment', 'Payment received ✅', '{amount} for {batch}. Your course is ready.', '/course/{course_id}'],
            ['enrollment.manual', 'payment', 'You are enrolled 🎉', 'You now have access to {batch}.', '/course/{course_id}'],
            ['test.result', 'test_result', 'Result: {test}', 'You scored {score}. Rank {rank}.', '/attempt/{attempt}/result'],
            ['enrollment.expiring', 'reminder', 'Your access ends soon', '{course} ends on {date}. Renew to keep learning.', '/course/{course_id}'],
            ['chat.message', 'chat', '{room}', '{sender}: {text}', '/chat/{room_id}'],
        ];
        foreach ($templates as [$key, $ch, $title, $body, $link]) {
            NotificationTemplate::updateOrCreate(['key' => $key], ['channel_key' => $ch, 'title' => $title, 'body' => $body, 'deep_link' => $link]);
        }

        // who may start a direct chat with whom
        $rules = [
            ['admin', '*', 'allow'], ['super_admin', '*', 'allow'], ['staff', '*', 'allow'],
            ['teacher', 'admin', 'allow'], ['teacher', 'staff', 'allow'], ['teacher', 'teacher', 'allow'], ['teacher', 'student', 'shared_batch'],
            ['student', 'teacher', 'shared_batch'], ['student', 'staff', 'support_only'], ['student', 'admin', 'deny'], ['student', 'student', 'deny'],
        ];
        foreach ($rules as [$from, $to, $rule]) {
            ChatPolicy::updateOrCreate(['from_role' => $from, 'to_role' => $to], ['rule' => $rule]);
        }

        foreach (['android' => 'https://play.google.com/store/apps/details?id=com.padanam.app', 'ios' => 'https://apps.apple.com/app/id0000000000', 'web' => null, 'windows' => null, 'macos' => null, 'linux' => null] as $platform => $url) {
            AppVersion::updateOrCreate(['platform' => $platform], ['latest_version' => '1.2.0', 'latest_build' => 12, 'min_supported_build' => 8, 'store_url' => $url, 'notes' => 'Courses, chat and class alerts']);
        }
    }
}
