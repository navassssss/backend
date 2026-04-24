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
            'class_id'        => 'required|exists:class_rooms,id',
            'date'            => 'required|date',
            'session'         => 'required|in:morning,afternoon',
            'absent_students' => 'present|array',
            'absent_students.*.id' => 'required|exists:students,id',
            'absent_students.*.reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $attendance = Attendance::create([
                'class_id'  => $validated['class_id'],
                'date'      => $validated['date'],
                'session'   => $validated['session'],
                'marked_by' => $request->user()->id,
            ]);

            $students  = Student::where('class_id', $validated['class_id'])->pluck('id');
            $absentSet = collect($validated['absent_students'])->keyBy('id'); // O(1) lookup

            // Single bulk insert instead of one INSERT per student
            $rows = $students->map(function ($sid) use ($attendance, $absentSet) {
                $isAbsent = $absentSet->has($sid);
                return [
                    'attendance_id' => $attendance->id,
                    'student_id'    => $sid,
                    'status'        => $isAbsent ? 'absent' : 'present',
                    'remarks'       => $isAbsent ? $absentSet->get($sid)['reason'] ?? null : null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            })->all();

            AttendanceRecord::insert($rows);
        });

        return response()->json(['message' => 'Attendance submitted successfully']);
    }

    // List attendance for a given date
    public function index(Request $request)
    {
        $date = $request->query('date', date('Y-m-d'));

        // Load everything with eager loading — no extra queries inside map()
        $attendances = Attendance::with([
            'classRoom:id,name',
            'marker:id,name',
            'records:id,attendance_id,student_id,status,remarks',
            'records.student:id,user_id,roll_number',
            'records.student.user:id,name',
        ])->where('date', $date)->get();

        $records = $attendances->map(function (Attendance $att) {
            // Use already-loaded collection — zero extra queries
            $recordCollection = $att->records;

            $present = $recordCollection->where('status', 'present')->count();
            $absent  = $recordCollection->where('status', 'absent')->count();

            $absentStudents = $recordCollection
                ->where('status', 'absent')
                ->map(fn ($r) => [
                    'id'          => $r->student?->id,
                    'name'        => $r->student?->user?->name ?? 'Unknown',
                    'roll_number' => $r->student?->roll_number,
                    'reason'      => $r->remarks,
                ])->values();

            return [
                'id'             => $att->id,
                'className'      => $att->classRoom?->name,
                'classId'        => $att->classRoom?->id,
                'session'        => $att->session,
                'teacherName'    => $att->marker?->name,
                'presentCount'   => $present,
                'absentCount'    => $absent,
                'absentStudents' => $absentStudents,
                'date'           => $att->date,
                'submittedAt'    => $att->created_at->toIso8601String(),
            ];
        });

        // Day-level stats from already-loaded data — no extra DB query
        $allRecords       = $attendances->flatMap(fn ($a) => $a->records);
        $morningRecords   = $allRecords->filter(fn ($r) => $r->attendance?->session === 'morning');
        $afternoonRecords = $allRecords->filter(fn ($r) => $r->attendance?->session === 'afternoon');

        return response()->json([
            'records'    => $records,
            'todayStats' => [
                'morningPresent'   => $morningRecords->where('status', 'present')->count(),
                'morningAbsent'    => $morningRecords->where('status', 'absent')->count(),
                'afternoonPresent' => $afternoonRecords->where('status', 'present')->count(),
                'afternoonAbsent'  => $afternoonRecords->where('status', 'absent')->count(),
            ],
        ]);
    }

    // Get specific attendance details
    public function show($id)
    {
        $attendance = Attendance::with([
            'classRoom:id,name',
            'marker:id,name',
            'records:id,attendance_id,student_id,status',
            'records.student:id,user_id,roll_number,name',
        ])->findOrFail($id);

        return response()->json([
            'id'          => $attendance->id,
            'className'   => $attendance->classRoom?->name,
            'session'     => $attendance->session,
            'date'        => $attendance->date,
            'teacherName' => $attendance->marker?->name,
            'records'     => $attendance->records->map(fn ($r) => [
                'studentId'   => $r->student_id,
                'studentName' => $r->student?->name ?? $r->student?->user?->name,
                'rollNumber'  => $r->student?->roll_number,
                'status'      => $r->status,
            ]),
        ]);
    }

    // Get all classes for dropdown — withCount replaces per-class count() query
    public function classes()
    {
        $classes = ClassRoom::select('id', 'name')
            ->withCount('students')
            ->get()
            ->map(fn ($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'studentCount' => $c->students_count,
            ]);

        return response()->json($classes);
    }

    // Get students for a class for taking attendance
    public function students($classId)
    {
        $students = Student::where('class_id', $classId)
            ->with('user:id,name')
            ->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'name'        => $s->user?->name ?? 'Unknown',
                'roll_number' => $s->roll_number,
                'photo'       => $s->photo,
            ]);

        return response()->json($students);
    }
}
