<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    // Check if attendance exists for class-date-session
    public function check(Request $request)
    {
        $exists = Attendance::where('class_id', $request->class_id)
            ->where('date', $request->date)
            ->where('session', $request->session)
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    // Submit attendance records
    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:class_rooms,id',
            'date' => 'required|date',
            'session' => 'required|in:morning,afternoon',
            'absent_students' => 'present|array', // Can be empty if everyone present
            'absent_students.*' => 'exists:students,id'
        ]);

        DB::transaction(function () use ($validated, $request) {
            // Create parent attendance record
            $attendance = Attendance::create([
                'class_id' => $validated['class_id'],
                'date' => $validated['date'],
                'session' => $validated['session'],
                'marked_by' => $request->user()->id
            ]);

            // Get all students in the class
            $students = Student::where('class_id', $validated['class_id'])->get();
            $absentIds = collect($validated['absent_students']);

            foreach ($students as $student) {
                $status = $absentIds->contains($student->id) ? 'absent' : 'present';
                
                AttendanceRecord::create([
                    'attendance_id' => $attendance->id,
                    'student_id' => $student->id,
                    'status' => $status
                ]);
            }
        });

        return response()->json(['message' => 'Attendance submitted successfully']);
    }

    // List recent attendance (for list view)
    public function index(Request $request)
    {
        $date = $request->query('date', date('Y-m-d'));

        $records = Attendance::with(['classRoom', 'marker', 'records.student.user'])
            ->where('date', $date)
            ->get()
            ->map(function ($att) {
                $present = $att->records()->where('status', 'present')->count();
                $absent = $att->records()->where('status', 'absent')->count();
                
                // Get absent students with their details
                $absentStudents = $att->records()
                    ->where('status', 'absent')
                    ->with('student.user')
                    ->get()
                    ->map(function($record) {
                        return [
                            'id' => $record->student->id,
                            'name' => $record->student->user->name ?? 'Unknown',
                            'roll_number' => $record->student->roll_number
                        ];
                    });

                return [
                    'id' => $att->id,
                    'className' => $att->classRoom->name,
                    'classId' => $att->classRoom->id,
                    'session' => $att->session,
                    'teacherName' => $att->marker->name,
                    'presentCount' => $present,
                    'absentCount' => $absent,
                    'absentStudents' => $absentStudents,
                    'date' => $att->date,
                    'submittedAt' => $att->created_at->toIso8601String()
                ];
            });

        return response()->json($records);
    }

    // Get specific attendance details
    public function show($id)
    {
        $attendance = Attendance::with(['classRoom', 'marker', 'records.student'])->findOrFail($id);
        
        return response()->json([
            'id' => $attendance->id,
            'className' => $attendance->classRoom->name,
            'session' => $attendance->session,
            'date' => $attendance->date,
            'teacherName' => $attendance->marker->name,
            'records' => $attendance->records->map(function ($r) {
                return [
                    'studentId' => $r->student_id,
                    'studentName' => $r->student->name,
                    'rollNumber' => $r->student->roll_number,
                    'status' => $r->status
                ];
            })
        ]);
    }
    

    // Get all classes for dropdown
    public function classes()
    {
        $classes = ClassRoom::select('id', 'name')->get()->map(function($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'studentCount' => $c->students()->count()
            ];
        });
        return response()->json($classes);
    }

    // Get students for a class for taking attendance
    public function students($classId)
    {
        $students = Student::where('class_id', $classId)
            ->with('user:id,name')
            ->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')
            ->get()
            ->map(function($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->user->name ?? 'Unknown',
                    'roll_number' => $student->roll_number,
                    'photo' => $student->photo
                ];
            });
            
        return response()->json($students);
    }
}
