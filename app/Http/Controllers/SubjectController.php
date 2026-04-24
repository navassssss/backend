<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Subject::with(['classRoom', 'teacher'])
            ->withCount('works as total_works_assigned')
            ->with(['works' => function ($q) {
                $q->withCount([
                    'submissions as total_submissions',
                    'submissions as evaluated_submissions' => function ($sq) {
                        $sq->whereNotNull('evaluated_at')->orWhere('status', 'evaluated');
                    }
                ]);
            }]);

        // Role-based filtering
        if ($user->role === 'teacher') {
            // Teachers see only subjects they teach
            $query->where('teacher_id', $user->id);
        }
        // Principals/managers see all subjects (no filter)

        $subjects = $query->get()
            ->map(function($subject) {
                $totalSubmissions = $subject->works->sum('total_submissions');
                $evaluatedSubmissions = $subject->works->sum('evaluated_submissions');
                $completionPercent = $totalSubmissions > 0 ? (int)round(($evaluatedSubmissions / $totalSubmissions) * 100) : 0;
                
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'className' => $subject->classRoom->name,
                    'classId' => $subject->class_id,
                    'teacherName' => $subject->teacher->name,
                    'teacherId' => $subject->teacher_id,
                    'finalMaxMarks' => $subject->final_max_marks,
                    'isLocked' => $subject->is_locked,
                    'completion_percent' => $completionPercent
                ];
            });

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'class_id' => 'required|exists:class_rooms,id',
            'teacher_id' => 'required|exists:users,id',
            'final_max_marks' => 'required|integer|min:1|max:100'
        ]);

        $subject = Subject::create($validated);

        return response()->json([
            'message' => 'Subject created successfully',
            'subject' => $subject
        ], 201);
    }

    public function show($id)
    {
        $subject = Subject::with(['classRoom', 'teacher', 'works'])->findOrFail($id);

        return response()->json([
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'className' => $subject->classRoom->name,
            'classId' => $subject->class_id,
            'teacherName' => $subject->teacher->name,
            'teacherId' => $subject->teacher_id,
            'finalMaxMarks' => $subject->final_max_marks,
            'isLocked' => $subject->is_locked,
            'worksCount' => $subject->works->count()
        ]);
    }

    public function update(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50',
            'teacher_id' => 'sometimes|exists:users,id',
            'final_max_marks' => 'sometimes|integer|min:1|max:100'
        ]);

        $subject->update($validated);

        return response()->json([
            'message' => 'Subject updated successfully',
            'subject' => $subject
        ]);
    }

    public function toggleLock($id)
    {
        $subject = Subject::findOrFail($id);
        $subject->is_locked = !$subject->is_locked;
        $subject->save();

        return response()->json([
            'message' => 'Subject lock status updated',
            'isLocked' => $subject->is_locked
        ]);
    }

    public function destroy($id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json([
            'message' => 'Subject deleted successfully'
        ]);
    }

    public function getSubjectStatistics($id)
    {
        $subject = Subject::with(['classRoom', 'teacher'])->findOrFail($id);

        // Precount total students once (not inside a loop)
        $totalStudents = \App\Models\Student::where('class_id', $subject->class_id)->count();

        // Load works with evaluated submission counts (no N+1)
        $works = \App\Models\CCEWork::where('subject_id', $id)
            ->withCount([
                'submissions as evaluated_count' => fn($q) => $q->whereNotNull('marks_obtained'),
            ])
            ->orderBy('deadline', 'desc')
            ->get();

        $totalWorks    = $works->count();
        $completedWorks = 0;
        $now           = now();

        $worksData = $works->map(function ($work) use (&$completedWorks, $totalStudents, $now) {
            $deadlinePassed = $work->deadline ? $now->gt($work->deadline) : false;
            $isCompleted    = $deadlinePassed && $work->evaluated_count > 0;
            if ($isCompleted) $completedWorks++;

            return [
                'id'              => $work->id,
                'title'           => $work->title,
                'description'     => $work->description,
                'max_marks'       => $work->max_marks,
                'deadline'        => $work->deadline?->toISOString(),
                'is_completed'    => $isCompleted,
                'evaluated_count' => $work->evaluated_count,
                'total_students'  => $totalStudents,
            ];
        });

        $totalPossibleMarks = $works->sum('max_marks');

        // Single SQL aggregation for all students in the class — no per-student loop
        $studentIds = \App\Models\Student::where('class_id', $subject->class_id)
            ->pluck('id');

        $obtainedMap = \DB::table('cce_submissions as s')
            ->join('cce_works as w', 's.work_id', '=', 'w.id')
            ->select('s.student_id', \DB::raw('SUM(s.marks_obtained) as total_obtained'))
            ->where('w.subject_id', $id)
            ->whereNotNull('s.marks_obtained')
            ->whereIn('s.student_id', $studentIds)
            ->groupBy('s.student_id')
            ->pluck('total_obtained', 'student_id');

        $students = \App\Models\Student::where('class_id', $subject->class_id)
            ->with('user:id,name')
            ->select('id', 'user_id', 'username')
            ->get();

        $studentMarks = $students->map(function ($student) use ($obtainedMap, $totalPossibleMarks, $subject) {
            $totalObtained  = (float) ($obtainedMap[$student->id] ?? 0);
            $aggregatedMarks = $totalPossibleMarks > 0
                ? ($totalObtained / $totalPossibleMarks) * $subject->final_max_marks : 0;
            $percentage      = $totalPossibleMarks > 0
                ? ($totalObtained / $totalPossibleMarks) * 100 : 0;

            return [
                'student_id'       => $student->id,
                'student_name'     => $student->user->name,
                'username'         => $student->username,
                'total_obtained'   => round($totalObtained, 2),
                'total_possible'   => $totalPossibleMarks,
                'aggregated_marks' => round($aggregatedMarks, 2),
                'percentage'       => round($percentage, 2),
            ];
        })->sortByDesc('aggregated_marks')->values();

        return response()->json([
            'subject' => [
                'id'           => $subject->id,
                'name'         => $subject->name,
                'code'         => $subject->code,
                'max_marks'    => $subject->final_max_marks,
                'class_id'     => $subject->class_id,
                'class_name'   => $subject->classRoom->name,
                'teacher_name' => $subject->teacher->name,
            ],
            'total_works'    => $totalWorks,
            'completed_works' => $completedWorks,
            'works'          => $worksData,
            'student_marks'  => $studentMarks,
        ]);
    }
}
