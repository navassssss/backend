<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\Outpass;
use App\Models\MedicalRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    // Check if attendance exists for class-date-session
    public function check(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('create', \App\Models\Attendance::class);

        $exists = Attendance::where('class_id', $request->class_id)
            ->where('date', $request->date)
            ->where('session', $request->session)
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    // Submit attendance records
    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('create', \App\Models\Attendance::class);

        $validated = $request->validate([
            'class_id' => 'required|exists:class_rooms,id',
            'date' => 'required|date',
            'session' => 'required|in:morning,afternoon',
            'absent_students' => 'present|array',
            'absent_students.*.id' => 'required|exists:students,id',
            'absent_students.*.reason' => 'nullable|string|max:255',
        ]);

        if (
            Attendance::where('class_id', $validated['class_id'])
                ->where('date', $validated['date'])
                ->where('session', $validated['session'])
                ->exists()
        ) {
            return response()->json(['message' => 'Attendance already submitted for this session'], 422);
        }

        DB::transaction(function () use ($validated, $request) {
            $attendance = Attendance::create([
                'class_id' => $validated['class_id'],
                'date' => $validated['date'],
                'session' => $validated['session'],
                'marked_by' => $request->user()->id,
            ]);

            $students = Student::where('class_id', $validated['class_id'])->academic()->pluck('id');
            $absentSet = collect($validated['absent_students'])->keyBy('id'); // O(1) lookup

            // Single bulk insert instead of one INSERT per student
            $rows = $students->map(function ($sid) use ($attendance, $absentSet) {
                $isAbsent = $absentSet->has($sid);
                return [
                    'attendance_id' => $attendance->id,
                    'student_id' => $sid,
                    'status' => $isAbsent ? 'absent' : 'present',
                    'remarks' => $isAbsent ? $absentSet->get($sid)['reason'] ?? null : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            AttendanceRecord::insert($rows);
        });

        return response()->json(['message' => 'Attendance submitted successfully']);
    }

    // List attendance for a given date
    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('viewAny', \App\Models\Attendance::class);

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
            $absent = $recordCollection->where('status', 'absent')->count();

            $absentStudents = $recordCollection
                ->where('status', 'absent')
                ->map(fn($r) => [
                    'id' => $r->student?->id,
                    'name' => $r->student?->user?->name ?? 'Unknown',
                    'roll_number' => $r->student?->roll_number,
                    'reason' => $r->remarks,
                ])->values();

            return [
                'id' => $att->id,
                'className' => $att->classRoom?->name,
                'classId' => $att->classRoom?->id,
                'session' => $att->session,
                'teacherName' => $att->marker?->name,
                'presentCount' => $present,
                'absentCount' => $absent,
                'absentStudents' => $absentStudents,
                'date' => $att->date,
                'submittedAt' => $att->created_at->toIso8601String(),
            ];
        });

        // Day-level stats from already-loaded data — no extra DB query
        $allRecords = $attendances->flatMap(fn($a) => $a->records);
        $morningRecords = $allRecords->filter(fn($r) => $r->attendance?->session === 'morning');
        $afternoonRecords = $allRecords->filter(fn($r) => $r->attendance?->session === 'afternoon');

        return response()->json([
            'records' => $records,
            'todayStats' => [
                'morningPresent' => $morningRecords->where('status', 'present')->count(),
                'morningAbsent' => $morningRecords->where('status', 'absent')->count(),
                'afternoonPresent' => $afternoonRecords->where('status', 'present')->count(),
                'afternoonAbsent' => $afternoonRecords->where('status', 'absent')->count(),
            ],
        ]);
    }

    // Get specific attendance details
    public function show($id)
    {
        \Illuminate\Support\Facades\Gate::authorize('view', \App\Models\Attendance::class);

        $attendance = Attendance::with([
            'classRoom:id,name',
            'marker:id,name',
            'records:id,attendance_id,student_id,status,remarks',
            'records.student:id,user_id,roll_number',
            'records.student.user:id,name',
        ])->findOrFail($id);

        return response()->json([
            'id' => $attendance->id,
            'className' => $attendance->classRoom?->name,
            'classId' => $attendance->class_id,
            'session' => $attendance->session,
            'date' => $attendance->date,
            'teacherName' => $attendance->marker?->name,
            'records' => $attendance->records->map(fn($r) => [
                'studentId' => $r->student_id,
                'studentName' => $r->student?->name ?? $r->student?->user?->name,
                'rollNumber' => $r->student?->roll_number,
                'status' => $r->status,
                'reason' => $r->remarks,
            ]),
        ]);
    }

    // Update existing attendance submission
    public function update(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        \Illuminate\Support\Facades\Gate::authorize('update', $attendance);

        $validated = $request->validate([
            'absent_students' => 'present|array',
            'absent_students.*.id' => 'required|exists:students,id',
            'absent_students.*.reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($attendance, $validated) {
            // Get all students for this class
            $students = Student::where('class_id', $attendance->class_id)->academic()->pluck('id');
            $absentSet = collect($validated['absent_students'])->keyBy('id');

            // Delete old records
            AttendanceRecord::where('attendance_id', $attendance->id)->delete();

            // Re-insert clean state based on new payload
            $rows = $students->map(function ($sid) use ($attendance, $absentSet) {
                $isAbsent = $absentSet->has($sid);
                return [
                    'attendance_id' => $attendance->id,
                    'student_id' => $sid,
                    'status' => $isAbsent ? 'absent' : 'present',
                    'remarks' => $isAbsent ? $absentSet->get($sid)['reason'] ?? null : null,
                    'created_at' => $attendance->created_at,
                    'updated_at' => now(),
                ];
            })->all();

            AttendanceRecord::insert($rows);
        });

        return response()->json(['message' => 'Attendance updated successfully']);
    }

    // Delete an entire attendance submission
    public function destroy($id)
    {
        $attendance = Attendance::findOrFail($id);
        \Illuminate\Support\Facades\Gate::authorize('delete', $attendance);

        DB::transaction(function () use ($attendance) {
            AttendanceRecord::where('attendance_id', $attendance->id)->delete();
            $attendance->delete();
        });

        return response()->json(['message' => 'Attendance record deleted successfully']);
    }

    // Get all classes for dropdown — withCount replaces per-class count() query
    public function classes()
    {
        \Illuminate\Support\Facades\Gate::authorize('create', \App\Models\Attendance::class);

        $classes = ClassRoom::select('id', 'name')
            ->academic()
            ->withCount('students')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'studentCount' => $c->students_count,
            ]);

        return response()->json($classes);
    }

    // Get students for a class for taking attendance
    public function students($classId)
    {
        \Illuminate\Support\Facades\Gate::authorize('create', \App\Models\Attendance::class);

        $students = Student::where('class_id', $classId)
            ->academic()
            ->with(['user:id,name', 'department'])
            ->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->user?->name ?? 'Unknown',
                'roll_number' => $s->roll_number,
                'photo' => $s->photo,
                'department' => $s->department?->name,
            ]);

        return response()->json($students);
    }

    // Operational Report for Principals
    public function operationalReport(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('viewOperationalReport', \App\Models\Attendance::class);

        $sessionParam = $request->query('session', date('H') >= 13 ? 'AN' : 'FN');
        $session = $sessionParam === 'AN' ? 'afternoon' : 'morning';
        $date = $request->query('date', date('Y-m-d'));

        $totalStudents = Student::academic()->count();

        $attendances = Attendance::with(['records.student.user', 'records.student.classRoom'])
            ->where('date', $date)
            ->get();

        $morningRecords = $attendances->where('session', 'morning')->flatMap->records;
        $afternoonRecords = $attendances->where('session', 'afternoon')->flatMap->records;

        $fnAttendanceCount = $morningRecords->where('status', 'present')->count();
        $anAttendanceCount = $afternoonRecords->where('status', 'present')->count();

        $cutoffTime = $session === 'morning'
            ? Carbon::parse($date . ' 07:45:00')
            : Carbon::parse($date . ' 14:00:00');

        $allOutpasses = Outpass::with(['student.classRoom', 'student.user'])
            ->whereDate('out_time', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('actual_in_time')
                    ->orWhereDate('actual_in_time', '>=', $date);
            })
            ->get();

        $activeOutpasses = $allOutpasses->filter(function ($pass) use ($cutoffTime) {
            if (is_null($pass->actual_in_time))
                return true;
            if (Carbon::parse($pass->actual_in_time)->lte($cutoffTime))
                return false;
            return true;
        });

        $allMedicalCases = MedicalRecord::with(['student.classRoom', 'student.user'])
            ->whereDate('reported_at', '<=', $date)
            ->whereNull('sent_home_at') // Ignore sent home, outpass handles them
            ->where(function ($query) use ($date) {
                $query->whereNull('recovered_at')
                    ->orWhereDate('recovered_at', '>=', $date);
            })
            ->get();

        $medicalCases = $allMedicalCases->filter(function ($med) use ($cutoffTime) {
            if (is_null($med->recovered_at))
                return true;
            if (Carbon::parse($med->recovered_at)->lte($cutoffTime))
                return false;
            return true;
        });

        $sessionRecords = $session === 'morning' ? $morningRecords : $afternoonRecords;

        $absentRecords = $sessionRecords->where('status', 'absent');

        $outpassStudentIds = $activeOutpasses->pluck('student_id')->toArray();
        $medicalStudentIds = $medicalCases->pluck('student_id')->toArray();

        $unexplainedAbsentCount = 0;
        $classAttendanceMap = [];

        foreach ($absentRecords as $record) {
            $student = $record->student;
            if (!$student)
                continue;

            $isOutpass = in_array($student->id, $outpassStudentIds);
            $isMedical = in_array($student->id, $medicalStudentIds);

            if ($isOutpass) {
                $marker = 'O';
            } else if ($isMedical) {
                $marker = 'M';
            } else {
                $unexplainedAbsentCount++;
                $marker = 'A';
            }

            $classId = $student->class_id;
            $className = $student->classRoom ? $student->classRoom->name : 'Unknown';

            if (!isset($classAttendanceMap[$classId])) {
                $classAttendanceMap[$classId] = [
                    'classId' => $classId,
                    'className' => $className,
                    'absentCount' => 0,
                    'students' => []
                ];
            }

            $classAttendanceMap[$classId]['absentCount']++;
            $classAttendanceMap[$classId]['students'][] = [
                'id' => $student->id,
                'name' => $student->user ? $student->user->name : 'Unknown',
                'marker' => $marker
            ];
        }

        $officialAbsences = [];
        $idCounter = 1;

        foreach ($medicalCases as $case) {
            // If they also have an outpass, the Outpass module takes precedence
            if (in_array($case->student_id, $outpassStudentIds))
                continue;

            $timeStr = $case->recovered_at
                ? 'Recovered ' . Carbon::parse($case->recovered_at)->format('h:i A')
                : 'Still in Medical';

            $officialAbsences[] = [
                'id' => $idCounter++,
                'class' => $case->student->classRoom ? $case->student->classRoom->name : '-',
                'student' => $case->student->user ? $case->student->user->name : '-',
                'reason' => 'Medical',
                'time' => $timeStr
            ];
        }

        foreach ($activeOutpasses as $pass) {
            $timeStr = $pass->actual_in_time
                ? 'Returned ' . Carbon::parse($pass->actual_in_time)->format('h:i A')
                : 'Still Outside';

            $officialAbsences[] = [
                'id' => $idCounter++,
                'class' => $pass->student->classRoom ? $pass->student->classRoom->name : '-',
                'student' => $pass->student->user ? $pass->student->user->name : '-',
                'reason' => 'Outpass',
                'time' => $timeStr
            ];
        }

        return response()->json([
            'summary' => [
                'totalStudents' => $totalStudents,
                'fnAttendance' => $fnAttendanceCount,
                'anAttendance' => $anAttendanceCount,
                'activeOutpasses' => $activeOutpasses->count(),
                'medicalCases' => $medicalCases->count(),
                'unexplainedAbsent' => $unexplainedAbsentCount
            ],
            'officialAbsences' => collect($officialAbsences)->sortByDesc('time')->values()->all(),
            'classAttendance' => collect(array_values($classAttendanceMap))->sortBy('className')->values()->all()
        ]);
    }
}
