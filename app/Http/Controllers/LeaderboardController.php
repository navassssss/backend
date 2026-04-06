<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    /**
     * Get student leaderboard (monthly or overall)
     */
    public function students(Request $request)
    {
        $type = $request->get('type', 'overall'); // monthly or overall

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
            // Overall leaderboard
            $query->orderByDesc('total_points');
        }

        $students = $query->limit(100)->get();

        // Add rank & growth
        $students = $students->map(function ($student, $index) use ($type) {
            $curr = $student->current_month_points ?? 0;
            $prev = $student->prev_month_points ?? 0;
            $growth = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : ($curr > 0 ? 100 : 0);

            return [
                'rank' => $index + 1,
                'student_id' => $student->id,
                'name' => $student->user->name,
                'username' => $student->username,
                'class_name' => $student->class?->name,
                'class_department' => $student->class?->department,
                'points' => $type === 'monthly' ? $curr : $student->total_points,
                'growth' => $growth,
                'stars' => $student->stars,
            ];
        });

        // Ensure proper ranking if there are ties or using exact returned order
        return response()->json($students);
    }

    /**
     * Get class leaderboard (monthly or overall)
     */
    public function classes(Request $request)
    {
        $type = $request->get('type', 'overall'); // monthly or overall

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

        // Add rank & growth
        $classes = $classes->map(function ($class, $index) use ($type) {
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
        });

        return response()->json($classes);
    }
}
