<?php

namespace App\Listeners;

use App\Events\AchievementApproved;
use App\Models\PointsLog;
use Illuminate\Support\Facades\DB;

class UpdatePointsOnAchievementApproval
{
    /**
     * Handle the event.
     */
    public function handle(AchievementApproved $event): void
    {
        $achievement = $event->achievement;
        $student = $achievement->student;
        $points = $achievement->points;

        DB::transaction(function () use ($achievement, $student, $points) {
            // 1. Add points to student
            $student->increment('total_points', $points);

            // 2. Add points to class (if applicable)
            if ($achievement->category->applies_to_class && $student->class) {
                $student->class->increment('total_points', $points);
            }

            // 3. Log the points
            PointsLog::create([
                'student_id' => $student->id,
                'class_id' => $student->class_id,
                'achievement_id' => $achievement->id,
                'points' => $points,
                'source' => 'achievement',
                'month' => now()->month,
                'year' => now()->year,
            ]);
        });
    }
}
