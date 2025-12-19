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

        if ($type === 'monthly') {
            // Calculate monthly points from points_logs
            $query->selectRaw('students.*, (
                SELECT COALESCE(SUM(points), 0) 
                FROM points_logs 
                WHERE points_logs.student_id = students.id 
                AND points_logs.month = ? 
                AND points_logs.year = ?
            ) as monthly_points', [now()->month, now()->year])
                ->orderByDesc('monthly_points');
        } else {
            // Overall leaderboard
            $query->orderByDesc('total_points');
        }

        $students = $query->limit(100)->get();

        // Add rank
        $students = $students->map(function ($student, $index) use ($type) {
            return [
                'rank' => $index + 1,
                'student_id' => $student->id,
                'name' => $student->user->name,
                'username' => $student->username,
                'class_name' => $student->class?->name,
                'class_department' => $student->class?->department,
                'points' => $type === 'monthly' ? $student->monthly_points : $student->total_points,
                'stars' => $student->stars,
            ];
        });

        return response()->json($students);
    }

    /**
     * Get class leaderboard (monthly or overall)
     */
    public function classes(Request $request)
    {
        $type = $request->get('type', 'overall'); // monthly or overall

        $query = ClassRoom::withCount('students');

        if ($type === 'monthly') {
            // Calculate monthly points from points_logs
            $query->selectRaw('class_rooms.*, (
                SELECT COALESCE(SUM(points), 0) 
                FROM points_logs 
                WHERE points_logs.class_id = class_rooms.id 
                AND points_logs.month = ? 
                AND points_logs.year = ?
            ) as monthly_points', [now()->month, now()->year])
                ->orderByDesc('monthly_points');
        } else {
            // Overall leaderboard
            $query->orderByDesc('total_points');
        }

        $classes = $query->get();

        // Add rank
        $classes = $classes->map(function ($class, $index) use ($type) {
            return [
                'rank' => $index + 1,
                'class_id' => $class->id,
                'class_name' => $class->name,
                'department' => $class->department,
                'points' => $type === 'monthly' ? $class->monthly_points : $class->total_points,
                'student_count' => $class->students_count,
            ];
        });

        return response()->json($classes);
    }
}
