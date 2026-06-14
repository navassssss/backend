<?php

namespace App\Listeners;

use App\Events\AchievementRevoked;
use App\Models\PointsLog;
use Illuminate\Support\Facades\DB;

class RevokePointsOnAchievementRejection
{
    public function handle(AchievementRevoked $event): void
    {
        $achievement = $event->achievement;
        $student = $achievement->student;
        $points = $achievement->points;

        DB::transaction(function () use ($achievement, $student, $points) {
            $student->decrement('total_points', $points);

            if ($achievement->category->applies_to_class && $student->class) {
                $student->class->decrement('total_points', $points);
            }

            PointsLog::where('source', 'achievement')
                ->where('achievement_id', $achievement->id)
                ->where('student_id', $student->id)
                ->delete();
        });

        // Rebuild leaderboard cache
        app(\App\Services\LeaderboardService::class)->computeAndCacheGlobalLeaderboard();
    }
}
