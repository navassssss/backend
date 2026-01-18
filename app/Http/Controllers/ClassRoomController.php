<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassRoomController extends Controller
{
    /**
     * Get all classes with their class teachers
     */
    public function index()
    {
        $classes = ClassRoom::with('classTeacher')
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'level' => $class->level,
                    'section' => $class->section,
                    'department' => $class->department,
                    'students_count' => $class->students()->count(),
                    'class_teacher' => $class->classTeacher ? [
                        'id' => $class->classTeacher->id,
                        'name' => $class->classTeacher->name,
                    ] : null,
                ];
            });

        return response()->json($classes);
    }

    /**
     * Assign a class teacher to a class
     */
    public function assignTeacher(Request $request, $id)
    {
        $request->validate([
            'teacher_id' => 'required|exists:users,id'
        ]);

        $class = ClassRoom::findOrFail($id);
        $class->class_teacher_id = $request->teacher_id;
        $class->save();

        $class->load('classTeacher');

        return response()->json([
            'message' => 'Class teacher assigned successfully',
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'class_teacher' => $class->classTeacher ? [
                    'id' => $class->classTeacher->id,
                    'name' => $class->classTeacher->name,
                ] : null,
            ]
        ]);
    }

    /**
     * Get comprehensive class report
     */
    public function getReport($id)
    {
        $class = ClassRoom::with(['classTeacher', 'students'])->findOrFail($id);

        // Get all students with their data
        $students = $class->students()->with(['user'])->get();

        // Calculate attendance statistics
        $attendanceStats = $this->calculateAttendanceStats($students);

        // Calculate academic performance
        $academicStats = $this->calculateAcademicStats($students);

        // Calculate achievements
        $achievementStats = $this->calculateAchievementStats($students);

        // Get student details with rankings
        $studentDetails = $this->getStudentDetails($students);

        return response()->json([
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'level' => $class->level,
                'section' => $class->section,
                'class_teacher' => $class->classTeacher ? [
                    'id' => $class->classTeacher->id,
                    'name' => $class->classTeacher->name,
                ] : null,
                'total_students' => $students->count(),
            ],
            'attendance' => $attendanceStats,
            'academic' => $academicStats,
            'achievements' => $achievementStats,
            'students' => $studentDetails,
        ]);
    }

    private function calculateAttendanceStats($students)
    {
        $today = now()->format('Y-m-d');
        $totalStudents = $students->count();
        
        if ($totalStudents === 0) {
            return [
                'average_percentage' => 0,
                'present_today' => 0,
                'absent_today' => 0,
                'total_days' => 0,
            ];
        }

        // Calculate today's attendance
        $presentToday = 0;
        $totalAttendancePercentage = 0;

        foreach ($students as $student) {
            // Get today's attendance records
            $todayRecords = DB::table('attendance_records')
                ->join('attendances', 'attendance_records.attendance_id', '=', 'attendances.id')
                ->where('attendance_records.student_id', $student->id)
                ->where('attendances.date', $today)
                ->where('attendance_records.status', 'present')
                ->count();

            if ($todayRecords > 0) {
                $presentToday++;
            }

            // Calculate overall attendance percentage
            $totalRecords = DB::table('attendance_records')
                ->where('student_id', $student->id)
                ->count();

            if ($totalRecords > 0) {
                $presentRecords = DB::table('attendance_records')
                    ->where('student_id', $student->id)
                    ->where('status', 'present')
                    ->count();

                $totalAttendancePercentage += ($presentRecords / $totalRecords) * 100;
            }
        }

        return [
            'average_percentage' => $totalStudents > 0 ? round($totalAttendancePercentage / $totalStudents, 2) : 0,
            'present_today' => $presentToday,
            'absent_today' => $totalStudents - $presentToday,
            'total_days' => DB::table('attendances')->distinct('date')->count('date'),
        ];
    }

    private function calculateAcademicStats($students)
    {
        try {
            $studentIds = $students->pluck('id');

            // Get all marks for these students with work details
            $marks = DB::table('cce_submissions')
                ->leftJoin('cce_works', 'cce_submissions.work_id', '=', 'cce_works.id')
                ->whereIn('cce_submissions.student_id', $studentIds)
                ->where('cce_submissions.status', 'evaluated') // Only count evaluated submissions
                ->whereNotNull('cce_submissions.marks_obtained') // Only count submissions with marks
                ->select(
                    'cce_submissions.student_id',
                    'cce_submissions.marks_obtained',
                    'cce_works.max_marks'
                )
                ->get();

            if ($marks->isEmpty()) {
                return [
                    'average_marks' => 0,
                    'top_performers' => [],
                    'marks_ranking' => [],
                ];
            }

            // Calculate average percentage per student
            $studentMarks = [];
            foreach ($students as $student) {
                $studentMarkRecords = $marks->where('student_id', $student->id);
                if ($studentMarkRecords->count() > 0) {
                    $totalMarks = 0;
                    $totalMaxMarks = 0;
                    
                    foreach ($studentMarkRecords as $mark) {
                        $totalMarks += $mark->marks_obtained ?? 0;
                        $totalMaxMarks += $mark->max_marks ?? 0;
                    }
                    
                    if ($totalMaxMarks > 0) {
                        $percentage = ($totalMarks / $totalMaxMarks) * 100;
                        $studentMarks[] = [
                            'student_id' => $student->id,
                            'student_name' => $student->name,
                            'roll_number' => $student->roll_number,
                            'average_marks' => round($percentage, 2),
                        ];
                    }
                }
            }

            // Sort by average marks
            usort($studentMarks, function ($a, $b) {
                return $b['average_marks'] <=> $a['average_marks'];
            });

            $overallAverage = count($studentMarks) > 0 
                ? round(array_sum(array_column($studentMarks, 'average_marks')) / count($studentMarks), 2)
                : 0;

            return [
                'average_marks' => $overallAverage,
                'top_performers' => array_slice($studentMarks, 0, 5),
                'marks_ranking' => $studentMarks,
            ];
        } catch (\Exception $e) {
            \Log::error('Error calculating academic stats: ' . $e->getMessage());
            // Return empty data if marks table doesn't exist
            return [
                'average_marks' => 0,
                'top_performers' => [],
                'marks_ranking' => [],
            ];
        }
    }

    private function calculateAchievementStats($students)
    {
        try {
            $studentIds = $students->pluck('id');

            // Get all approved achievements for these students
            $achievements = DB::table('achievements')
                ->whereIn('student_id', $studentIds)
                ->where('status', 'approved')
                ->get();

            if ($achievements->isEmpty()) {
                return [
                    'total_points' => 0,
                    'total_achievements' => 0,
                    'top_achievers' => [],
                    'recent_achievements' => [],
                ];
            }

            // Calculate points per student
            $studentAchievements = [];
            foreach ($students as $student) {
                $studentAchievementRecords = $achievements->where('student_id', $student->id);
                if ($studentAchievementRecords->count() > 0) {
                    $totalPoints = $studentAchievementRecords->sum('points');
                    $studentAchievements[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->name,
                        'roll_number' => $student->roll_number,
                        'total_points' => $totalPoints,
                        'achievement_count' => $studentAchievementRecords->count(),
                    ];
                }
            }

            // Sort by total points
            usort($studentAchievements, function ($a, $b) {
                return $b['total_points'] <=> $a['total_points'];
            });

            // Get recent achievements with student info
            $recentAchievements = DB::table('achievements')
                ->join('students', 'achievements.student_id', '=', 'students.id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereIn('achievements.student_id', $studentIds)
                ->where('achievements.status', 'approved')
                ->select('achievements.*', 'users.name as student_name', 'students.roll_number')
                ->orderBy('achievements.created_at', 'desc')
                ->limit(10)
                ->get();

            return [
                'total_points' => $achievements->sum('points'),
                'total_achievements' => $achievements->count(),
                'top_achievers' => array_slice($studentAchievements, 0, 5),
                'recent_achievements' => $recentAchievements,
            ];
        } catch (\Exception $e) {
            // Return empty data if there's an error
            return [
                'total_points' => 0,
                'total_achievements' => 0,
                'top_achievers' => [],
                'recent_achievements' => [],
            ];
        }
    }

    private function getStudentDetails($students)
    {
        $studentDetails = [];

        foreach ($students as $student) {
            try {
                // Get attendance percentage
                $totalRecords = DB::table('attendance_records')
                    ->where('student_id', $student->id)
                    ->count();

                $attendancePercentage = 0;
                if ($totalRecords > 0) {
                    $presentRecords = DB::table('attendance_records')
                        ->where('student_id', $student->id)
                        ->where('status', 'present')
                        ->count();
                    $attendancePercentage = round(($presentRecords / $totalRecords) * 100, 2);
                }

                // Get average marks as percentage
                $avgMarks = 0;
                try {
                    $marksData = DB::table('cce_submissions')
                        ->leftJoin('cce_works', 'cce_submissions.work_id', '=', 'cce_works.id')
                        ->where('cce_submissions.student_id', $student->id)
                        ->where('cce_submissions.status', 'evaluated') // Only count evaluated submissions
                        ->whereNotNull('cce_submissions.marks_obtained') // Only count submissions with marks
                        ->select('cce_submissions.marks_obtained', 'cce_works.max_marks')
                        ->get();
                    
                    if ($marksData->count() > 0) {
                        $totalMarks = 0;
                        $totalMaxMarks = 0;
                        
                        foreach ($marksData as $mark) {
                            $totalMarks += $mark->marks_obtained ?? 0;
                            $totalMaxMarks += $mark->max_marks ?? 0;
                        }
                        
                        if ($totalMaxMarks > 0) {
                            $avgMarks = ($totalMarks / $totalMaxMarks) * 100;
                        }
                    }
                } catch (\Exception $e) {
                    // Marks table doesn't exist
                }

                // Get achievement points
                $achievementPoints = DB::table('achievements')
                    ->where('student_id', $student->id)
                    ->where('status', 'approved')
                    ->sum('points');

                $studentDetails[] = [
                    'id' => $student->id,
                    'name' => $student->name,
                    'roll_number' => $student->roll_number,
                    'attendance_percentage' => $attendancePercentage,
                    'average_marks' => $avgMarks ? round($avgMarks, 2) : 0,
                    'achievement_points' => $achievementPoints ?? 0,
                ];
            } catch (\Exception $e) {
                // Skip this student if there's an error
                continue;
            }
        }

        // Sort by roll number
        usort($studentDetails, function ($a, $b) {
            return strcmp($a['roll_number'], $b['roll_number']);
        });

        return $studentDetails;
    }
}
