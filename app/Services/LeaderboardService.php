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

        Cache::put('leaderboard:departments:overall', $this->computeDepartments('overall'), now()->addMinutes(15));
        Cache::put('leaderboard:departments:monthly', $this->computeDepartments('monthly'), now()->addMinutes(15));

        Cache::put('leaderboard:last_updated', now()->toIso8601String(), now()->addMinutes(15));
    }

    private function computeStudents(string $type)
    {
        $query = Student::with(['user', 'class'])->academic();

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
        $query = ClassRoom::academic()->withCount('students');

        $cm = now()->month;
        $cy = now()->year;
        $pm = now()->subMonth()->month;
        $py = now()->subMonth()->year;

        $query->selectRaw('class_rooms.*, 
            (SELECT COALESCE(SUM(points), 0) FROM points_logs WHERE points_logs.class_id = class_rooms.id AND points_logs.month = ? AND points_logs.year = ?) as current_month_points,
            (SELECT COALESCE(SUM(points), 0) FROM points_logs WHERE points_logs.class_id = class_rooms.id AND points_logs.month = ? AND points_logs.year = ?) as prev_month_points
        ', [$cm, $cy, $pm, $py]);

        $classes = $query->get();

        $mapped = $classes->map(function ($class) use ($type) {
            $curr = $class->current_month_points ?? 0;
            $prev = $class->prev_month_points ?? 0;
            $growth = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : ($curr > 0 ? 100 : 0);

            $points = $type === 'monthly' ? $curr : $class->total_points;
            $studentsCount = $class->students_count ?: 0;
            $average = $studentsCount > 0 ? round($points / $studentsCount, 2) : 0.00;

            return [
                'class_id' => $class->id,
                'class_name' => $class->name,
                'department' => $class->department,
                'points' => $points,
                'growth' => $growth,
                'student_count' => $studentsCount,
                'average' => $average,
            ];
        });

        $sorted = $mapped->sortByDesc('average')->values();

        return $sorted->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        })->toArray();
    }

    private function computeDepartments(string $type)
    {
        $cm = now()->month;
        $cy = now()->year;
        $pm = now()->subMonth()->month;
        $py = now()->subMonth()->year;

        $departments = \App\Models\Department::withCount('students')->get();

        $mapped = $departments->map(function ($department) use ($type, $cm, $cy, $pm, $py) {
            $studentIds = $department->students()->pluck('id');

            if ($type === 'monthly') {
                $points = \App\Models\PointsLog::whereIn('student_id', $studentIds)
                    ->where('month', $cm)
                    ->where('year', $cy)
                    ->sum('points');
            } else {
                $points = $department->students()->sum('total_points');
            }

            $prev = \App\Models\PointsLog::whereIn('student_id', $studentIds)
                ->where('month', $pm)
                ->where('year', $py)
                ->sum('points');
            
            $growth = $prev > 0 ? round((($points - $prev) / $prev) * 100, 1) : ($points > 0 ? 100 : 0);

            return [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'student_count' => $department->students_count,
                'points' => (int) $points,
                'growth' => $growth,
            ];
        });

        $sorted = $mapped->sortByDesc('points')->values();

        return $sorted->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        })->toArray();
    }
}
