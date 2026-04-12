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
        // Search by name (in users table) or roll number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                })
                ->orWhere('roll_number', 'like', "%{$search}%");
            });
        }

        // Filter by class level
        if ($request->filled('class')) {
            $classValue = $request->class;
            $query->whereHas('classRoom', function ($q) use ($classValue) {
                $q->where('level', $classValue)
                  ->orWhere(function ($subQ) use ($classValue) {
                      $subQ->where(function ($nameQ) use ($classValue) {
                          $nameQ->where('name', $classValue)
                                ->orWhere('name', "Class {$classValue}")
                                ->orWhere('name', 'like', "{$classValue}%")
                                ->orWhere('name', 'like', "Class {$classValue}%");
                      })->where(function ($notQ) use ($classValue) {
                          foreach (range(0, 9) as $digit) {
                              $notQ->where('name', 'not like', "{$classValue}{$digit}%")
                                   ->where('name', 'not like', "Class {$classValue}{$digit}%");
                          }
                      });
                  });
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
            // Sort by user name
            $query->join('users', 'students.user_id', '=', 'users.id')
                  ->orderBy('users.name')
                  ->select('students.*'); // Avoid ambiguous column errors
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

        // Default to last 90 days; caller can override with ?days=180 etc.
        $days  = max(1, (int) $request->query('days', 90));
        $since = now()->subDays($days)->format('Y-m-d');

        // Scoped load — not full history
        $allRecords = \App\Models\AttendanceRecord::where('student_id', $student->id)
            ->whereHas('attendance', fn($q) => $q->where('date', '>=', $since))
            ->with(['attendance:id,date,session,class_id', 'attendance.classRoom:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group records by date to calculate daily attendance
        $dailyAttendance = $allRecords->groupBy(function ($record) {
            return $record->attendance->date;
        })->map(function ($dayRecords) {
            $morning   = $dayRecords->firstWhere('attendance.session', 'morning');
            $afternoon = $dayRecords->firstWhere('attendance.session', 'afternoon');

            $morningPresent   = $morning   && $morning->status   === 'present';
            $afternoonPresent = $afternoon && $afternoon->status === 'present';

            if ($morningPresent && $afternoonPresent)   return 1;
            if (!$morningPresent && !$afternoonPresent) return 0;
            return 0.5;
        });

        $totalDays   = $dailyAttendance->count();
        $presentDays = $dailyAttendance->sum();
        $absentDays  = $totalDays - $presentDays;
        $percentage  = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;

        // Today's attendance
        $today       = now()->format('Y-m-d');
        $todayRecords = $allRecords->filter(fn($r) => $r->attendance->date === $today);

        $todayMorning   = $todayRecords->firstWhere('attendance.session', 'morning');
        $todayAfternoon = $todayRecords->firstWhere('attendance.session', 'afternoon');

        // Absent dates (last 20)
        $absentDates = $allRecords
            ->groupBy(fn($r) => $r->attendance->date)
            ->map(function ($records, $date) {
                $absentSessions = $records->where('status', 'absent')->pluck('attendance.session')->toArray();
                if (empty($absentSessions)) return null;
                return [
                    'date'      => $date,
                    'sessions'  => $absentSessions,
                    'count'     => count($absentSessions),
                    'isFullDay' => count($absentSessions) === 2,
                ];
            })
            ->filter()
            ->values()
            ->sortByDesc('date')
            ->take(20);

        // Recent records (last 10 dates)
        $recentRecords = $allRecords
            ->groupBy(fn($r) => $r->attendance->date)
            ->map(function ($records, $date) {
                $morning   = $records->firstWhere('attendance.session', 'morning');
                $afternoon = $records->firstWhere('attendance.session', 'afternoon');
                return [
                    'date'      => $date,
                    'morning'   => $morning   ? ['status' => $morning->status,   'className' => $morning->attendance->classRoom->name   ?? 'Unknown'] : null,
                    'afternoon' => $afternoon ? ['status' => $afternoon->status, 'className' => $afternoon->attendance->classRoom->name ?? 'Unknown'] : null,
                ];
            })
            ->sortByDesc('date')
            ->take(10)
            ->values();

        return response()->json([
            'student'      => ['id' => $student->id, 'name' => $student->name],
            'range_days'   => $days,
            'overallStats' => [
                'totalDays'   => $totalDays,
                'presentDays' => $presentDays,
                'absentDays'  => $absentDays,
                'percentage'  => $percentage,
            ],
            'today' => [
                'morning'   => $todayMorning   ? ['status' => $todayMorning->status,   'className' => $todayMorning->attendance->classRoom->name   ?? 'Unknown'] : null,
                'afternoon' => $todayAfternoon ? ['status' => $todayAfternoon->status, 'className' => $todayAfternoon->attendance->classRoom->name ?? 'Unknown'] : null,
            ],
            'absentDates'   => $absentDates,
            'recentRecords' => $recentRecords,
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

    /**
     * Bulk create students from array
     */
    public function bulkCreate(Request $request)
    {
        $validated = $request->validate([
            'students' => 'required|array',
            'students.*.id' => 'nullable|integer|exists:students,id',
            'students.*.name' => 'required|string',
            'students.*.roll_number' => 'nullable|string',
            'students.*.class_id' => 'nullable|exists:class_rooms,id',
        ]);

        $createdStudents = [];

        foreach ($validated['students'] as $studentData) {
            
            // 1) UPDATE EXISTING STUDENT
            if (!empty($studentData['id'])) {
                $existingStudent = Student::find($studentData['id']);
                
                // Ensure new roll number is not taken by someone else
                if (!empty($studentData['roll_number']) && $existingStudent->roll_number !== $studentData['roll_number']) {
                    $conflict = Student::where('roll_number', $studentData['roll_number'])
                        ->where('id', '!=', $studentData['id'])
                        ->first();
                    if ($conflict) {
                        return response()->json(['message' => "Admission number {$studentData['roll_number']} is already assigned to another student."], 422);
                    }
                }

                $existingStudent->update([
                    'class_id' => $studentData['class_id'] ?? $existingStudent->class_id,
                    'roll_number' => $studentData['roll_number'] ?? $existingStudent->roll_number,
                    'username' => !empty($studentData['roll_number']) ? 'st_' . $studentData['roll_number'] : $existingStudent->username,
                ]);

                if ($existingStudent->user) {
                    $existingStudent->user->update(['name' => $studentData['name']]);
                }
                
                $createdStudents[] = $existingStudent;
                continue;
            }

            // 2) CREATE NEW STUDENT
            // Check if roll_number is taken globally
            if (!empty($studentData['roll_number'])) {
                $conflict = Student::where('roll_number', $studentData['roll_number'])->first();
                if ($conflict) {
                    return response()->json(['message' => "Admission number {$studentData['roll_number']} is already taken. Please verify your list."], 422);
                }
            }

            // Create User safely
            $user = \App\Models\User::create([
                'name' => $studentData['name'],
                'email' => 'temp' . uniqid() . '@student.com', // temporary
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'student'
            ]);
            
            // Re-generate deterministic unique email
            $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $studentData['name']));
            $user->update(['email' => $cleanName . $user->id . '@student.com']);

            $rollObj = $studentData['roll_number'] ?? ('TEMP' . $user->id);

            // Create Student
            $student = Student::create([
                'user_id' => $user->id,
                'class_id' => $studentData['class_id'] ?? null,
                'username' => 'st_' . $rollObj,
                'roll_number' => $studentData['roll_number'] ?? null,
                'total_points' => 0,
                'wallet_balance' => 0,
                'opening_balance' => 0,
                'monthly_fee' => 0,
            ]);

            $student->load('user', 'classRoom');
            $createdStudents[] = $student;
        }

        return response()->json([
            'message' => 'Successfully processed ' . count($createdStudents) . ' students',
            'data' => collect($createdStudents)->take(10)
        ], 201);
    }

    /**
     * Bulk delete students
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        $students = Student::whereIn('id', $validated['student_ids'])->get();

        foreach ($students as $student) {
            // Delete user if it exists
            if ($student->user) {
                $student->user->delete();
            }
            $student->delete();
        }

        return response()->json(['message' => 'Students deleted successfully']);
    }
}
