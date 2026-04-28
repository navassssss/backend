<?php

namespace App\Services;

use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;

class LeaderboardService
{
    /**
     * Get the leaderboard entirely from memory. Never hits the DB directly on user-request.
     * ZERO DB hit when cached.
     *
     * @param string $entity "students" or "classes"
     * @param string $type "overall" or "monthly"
     * @return array
     */
    public function getLeaderboard(string $entity, string $type)
    {
        $cacheKey = "leaderboard:{$entity}:{$type}";

        if (!Cache::has($cacheKey)) {
            // Dispatch async background rebuild if empty, protecting DB from request stampedes
            dispatch(function () {
                app(\App\Services\LeaderboardService::class)->computeAndCacheGlobalLeaderboard();
            });
        }

        return Cache::get($cacheKey, []); // Always returns memory payload (or empty until first cron resolves)
    }

    /**
     * Cron-scheduled function to rebuild everything natively in the background.
     * Prevents any single user from bearing the aggregation cost.
     */
    public function computeAndCacheGlobalLeaderboard()
    {
        Cache::put('leaderboard:students:overall', $this->computeStudents('overall'), now()->addMinutes(15));
        Cache::put('leaderboard:students:monthly', $this->computeStudents('monthly'), now()->addMinutes(15));
        
        Cache::put('leaderboard:classes:overall', $this->computeClasses('overall'), now()->addMinutes(15));
        Cache::put('leaderboard:classes:monthly', $this->computeClasses('monthly'), now()->addMinutes(15));

        Cache::put('leaderboard:last_updated', now()->toIso8601String(), now()->addMinutes(15));
    }

    private function computeStudents(string $type)
    {
        $query = Student::with(['user', 'class']);

        $cm = now()->month;
        $cy = now()->year;
        $pm = now()->subMonth()->month;
        $py = now()->subMonth()->year;

        $query->selectRaw('students.*, 
            (SELECT COALESCE(SUM(points), 0) FROM points_logs WHERE points_logs.student_id = students.id AND points_logs.month = ? AND points_logs.year = ?) as current_month_points,
            (SELECT COALESCE(SUM(points), 0) FROM points_logs WHERE points_logs.student_id = students.id AND points_logs.month = ? AND points_logs.year = ?) as prev_month_points
        ', [$cm, $cy, $pm, $py]);

        if ($type === 'monthly') {
            $query->orderBy('current_month_points', 'desc');
        } else {
            $query->orderByDesc('total_points');
        }

        $students = $query->limit(100)->get();

        return $students->map(function ($student, $index) use ($type) {
            $curr = $student->current_month_points ?? 0;
            $prev = $student->prev_month_points ?? 0;
            $growth = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : ($curr > 0 ? 100 : 0);

            return [
                'rank' => $index + 1,
                'student_id' => $student->id,
                'name' => $student->user->name ?? 'Unknown',
                'username' => $student->username,
                'class_name' => $student->class?->name,
                'class_department' => $student->class?->department,
                'points' => $type === 'monthly' ? $curr : $student->total_points,
                'growth' => $growth,
                'stars' => $student->stars,
            ];
        })->toArray();
    }

    private function computeClasses(string $type)
    {
        $query = ClassRoom::withCount('students');

        $cm = now()->month;
        $cy = now()->year;
        $pm = now()->subMonth()->month;
        $py = now()->subMonth()->year;

        $query->selectRaw('class_rooms.*, 
            (SELECT COALESCE(SUM(points), 0) FROM points_logs WHERE points_logs.class_id = class_rooms.id AND points_logs.month = ? AND points_logs.year = ?) as current_month_points,
            (SELECT COALESCE(SUM(points), 0) FROM points_logs WHERE points_logs.class_id = class_rooms.id AND points_logs.month = ? AND points_logs.year = ?) as prev_month_points
        ', [$cm, $cy, $pm, $py]);

        if ($type === 'monthly') {
            $query->orderBy('current_month_points', 'desc');
        } else {
            $query->orderByDesc('total_points');
        }

        $classes = $query->get();

        return $classes->map(function ($class, $index) use ($type) {
            $curr = $class->current_month_points ?? 0;
            $prev = $class->prev_month_points ?? 0;
            $growth = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : ($curr > 0 ? 100 : 0);

            return [
                'rank' => $index + 1,
                'class_id' => $class->id,
                'class_name' => $class->name,
                'department' => $class->department,
                'points' => $type === 'monthly' ? $curr : $class->total_points,
                'growth' => $growth,
                'student_count' => $class->students_count,
            ];
        })->toArray();
    }
}
