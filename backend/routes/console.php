<?php

use Illuminate\Support\Facades\Schedule;

// Class alert ring N minutes before start (FCM class_alert channel)
Schedule::command('live:send-alerts')->everyMinute()->withoutOverlapping();
// Expire ended enrollments + remove from batch chat
Schedule::command('enrollments:expire')->hourly();
// Nightly activity roll-up for active/inactive monitoring
Schedule::command('stats:daily')->dailyAt('01:30')->timezone('Asia/Kolkata');

// Daily quiz auto-build + publish at 6 AM IST
Schedule::command('daily:publish')->dailyAt('06:00')->timezone('Asia/Kolkata');
// Keep ranks/percentiles fresh as more students attempt
Schedule::command('tests:rerank')->hourly();

// Unpaid payment links / checkouts (checks Razorpay once before expiring)
Schedule::command('orders:expire-links')->everyFifteenMinutes();
// Scheduled push campaigns
Schedule::command('campaigns:dispatch')->everyMinute()->withoutOverlapping();
