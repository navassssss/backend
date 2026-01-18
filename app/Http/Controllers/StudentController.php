<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * List all students with pagination and filters
     */
    public function index(Request $request)
    {
        $query = Student::with(['classRoom', 'user']);

        // Search by name or roll number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('roll_number', 'like', "%{$search}%");
            });
        }

        // Filter by class level
        if ($request->filled('class')) {
            $classValue = $request->class;
            $query->whereHas('classRoom', function ($q) use ($classValue) {
                $q->where('level', $classValue)
                  ->orWhere('name', 'like', "%Class {$classValue}%")
                  ->orWhere('name', 'like', "%{$classValue}%");
            });
        }

        // Filter by section
        if ($request->filled('section')) {
            $query->whereHas('classRoom', function ($q) use ($request) {
                $q->where('section', $request->section);
            });
        }

        // Dynamic sorting
        $sortBy = $request->get('sort', 'name');
        if ($sortBy === 'roll_number') {
            $query->orderBy('roll_number');
        } else {
            $query->orderBy('name');
        }

        // Paginate results
        $perPage = $request->get('per_page', 20);
        $students = $query->paginate($perPage);

        return response()->json($students);
    }

    /**
     * Get individual student details
     */
    public function showById($id)
    {
        $student = Student::with(['classRoom', 'user'])
            ->findOrFail($id);

        return response()->json($student);
    }

    /**
     * Get attendance records for a specific student
     */
    public function getAttendance($id, Request $request)
    {
        $student = Student::findOrFail($id);
        
        // Get all attendance records for this student
        $allRecords = \App\Models\AttendanceRecord::where('student_id', $student->id)
            ->with(['attendance.classRoom', 'attendance.marker'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Group records by date to calculate daily attendance
        $dailyAttendance = $allRecords->groupBy(function($record) {
            return $record->attendance->date;
        })->map(function($dayRecords) {
            $morning = $dayRecords->firstWhere('attendance.session', 'morning');
            $afternoon = $dayRecords->firstWhere('attendance.session', 'afternoon');
            
            // A day is considered present if BOTH sessions are present
            // A day is considered absent if BOTH sessions are absent
            // If mixed (one present, one absent), count as 0.5 present
            $morningPresent = $morning && $morning->status === 'present';
            $afternoonPresent = $afternoon && $afternoon->status === 'present';
            
            if ($morningPresent && $afternoonPresent) {
                return 1; // Full day present
            } elseif (!$morningPresent && !$afternoonPresent) {
                return 0; // Full day absent
            } else {
                return 0.5; // Half day (one session present, one absent)
            }
        });
        
        // Calculate overall stats
        $totalDays = $dailyAttendance->count();
        $presentDays = $dailyAttendance->sum(); // Sum of all values (1, 0.5, or 0)
        $absentDays = $totalDays - $presentDays;
        $percentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;
        
        // Get today's attendance
        $today = now()->format('Y-m-d');
        $todayRecords = $allRecords->filter(function($record) use ($today) {
            return $record->attendance->date === $today;
        });
        
        $todayMorning = $todayRecords->firstWhere('attendance.session', 'morning');
        $todayAfternoon = $todayRecords->firstWhere('attendance.session', 'afternoon');
        
        // Get absent dates (grouped by date)
        $absentDates = $allRecords
            ->groupBy(function($record) {
                return $record->attendance->date;
            })
            ->map(function($records, $date) {
                $absentSessions = $records->where('status', 'absent')->pluck('attendance.session')->toArray();
                if (empty($absentSessions)) {
                    return null;
                }
                return [
                    'date' => $date,
                    'sessions' => $absentSessions,
                    'count' => count($absentSessions),
                    'isFullDay' => count($absentSessions) === 2
                ];
            })
            ->filter()
            ->values()
            ->sortByDesc('date')
            ->take(20); // Last 20 absent dates
        
        // Recent records for display (grouped by date)
        $recentRecords = $allRecords
            ->groupBy(function($record) {
                return $record->attendance->date;
            })
            ->map(function($records, $date) {
                $morning = $records->firstWhere('attendance.session', 'morning');
                $afternoon = $records->firstWhere('attendance.session', 'afternoon');
                
                return [
                    'date' => $date,
                    'morning' => $morning ? [
                        'status' => $morning->status,
                        'className' => $morning->attendance->classRoom->name ?? 'Unknown'
                    ] : null,
                    'afternoon' => $afternoon ? [
                        'status' => $afternoon->status,
                        'className' => $afternoon->attendance->classRoom->name ?? 'Unknown'
                    ] : null
                ];
            })
            ->sortByDesc('date')
            ->take(10)
            ->values();
        
        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'overallStats' => [
                'totalDays' => $totalDays,
                'presentDays' => $presentDays,
                'absentDays' => $absentDays,
                'percentage' => $percentage
            ],
            'today' => [
                'morning' => $todayMorning ? [
                    'status' => $todayMorning->status,
                    'className' => $todayMorning->attendance->classRoom->name ?? 'Unknown'
                ] : null,
                'afternoon' => $todayAfternoon ? [
                    'status' => $todayAfternoon->status,
                    'className' => $todayAfternoon->attendance->classRoom->name ?? 'Unknown'
                ] : null
            ],
            'absentDates' => $absentDates,
            'recentRecords' => $recentRecords
        ]);
    }

    /**
     * Get student by username (public profile)
     */
    public function show(string $username)
    {
        $student = Student::where('username', $username)
            ->with(['user', 'class', 'achievements' => function ($query) {
                $query->where('status', 'approved')
                    ->with('category')
                    ->latest()
                    ->limit(10);
            }])
            ->firstOrFail();

        return response()->json($student);
    }

    /**
     * Get current student's profile
     */
    public function profile(Request $request)
    {
        $student = $request->user()->student;
        $student->load(['class', 'achievements.category']);

        return response()->json($student);
    }

    /**
     * Update current student's profile
     */
    public function update(Request $request)
    {
        $student = $request->user()->student;

        $validated = $request->validate([
            'photo' => 'nullable|string',
        ]);

        $student->update($validated);

        return response()->json($student);
    }
}
