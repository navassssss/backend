<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:remind-unread')->hourly();

// Precompute heavy Leaderboard statistics perfectly async every 10 minutes
Schedule::call(function (\App\Services\LeaderboardService $leaderboardService) {
    $leaderboardService->computeAndCacheGlobalLeaderboard();
})->everyTenMinutes()->name('compute-leaderboards')->withoutOverlapping();
