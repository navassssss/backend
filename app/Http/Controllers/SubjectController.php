<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Subject::with(['classRoom', 'teacher']);

        // Role-based filtering
        if ($user->role === 'teacher') {
            // Teachers see only subjects they teach
            $query->where('teacher_id', $user->id);
        }
        // Principals/managers see all subjects (no filter)

        $subjects = $query->get()
            ->map(function($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'className' => $subject->classRoom->name,
                    'classId' => $subject->class_id,
                    'teacherName' => $subject->teacher->name,
                    'teacherId' => $subject->teacher_id,
                    'finalMaxMarks' => $subject->final_max_marks,
                    'isLocked' => $subject->is_locked
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

    public function getSubjectStatistics($id)
    {
        $subject = Subject::with(['classRoom', 'teacher'])->findOrFail($id);
        
        // Get all CCE works for this subject
        $works = \App\Models\CCEWork::where('subject_id', $id)
            ->with(['submissions' => function($query) {
                $query->whereNotNull('marks_obtained');
            }])
            ->orderBy('deadline', 'desc')
            ->get();

        $totalWorks = $works->count();
        $completedWorks = 0;
        
        $worksData = $works->map(function($work) use (&$completedWorks, $subject) {
            $now = now();
            $deadlinePassed = $work->deadline ? $now->gt($work->deadline) : false;
            $evaluatedCount = $work->submissions->count();
            
            // Work is completed if deadline passed AND some students are evaluated
            $isCompleted = $deadlinePassed && $evaluatedCount > 0;
            if ($isCompleted) {
                $completedWorks++;
            }
            
            // Get total students from the subject's class
            $totalStudents = \App\Models\Student::where('class_id', $subject->class_id)->count();
            
            return [
                'id' => $work->id,
                'title' => $work->title,
                'description' => $work->description,
                'max_marks' => $work->max_marks,
                'deadline' => $work->deadline ? $work->deadline->toISOString() : null,
                'is_completed' => $isCompleted,
                'evaluated_count' => $evaluatedCount,
                'total_students' => $totalStudents
            ];
        });

        // Calculate student marks aggregation
        $students = \App\Models\Student::where('class_id', $subject->class_id)
            ->with(['user'])
            ->get();

        // Calculate total possible marks from ALL works in this subject
        $totalPossibleMarks = $works->sum('max_marks');
        
        $studentMarks = $students->map(function($student) use ($id, $subject, $totalPossibleMarks) {
            // Get all evaluated submissions for this student in this subject
            $submissions = \App\Models\CCESubmission::whereHas('work', function($query) use ($id) {
                $query->where('subject_id', $id);
            })
            ->where('student_id', $student->id)
            ->whereNotNull('marks_obtained')
            ->with('work')
            ->get();

            $totalObtained = $submissions->sum('marks_obtained');

            // Calculate aggregated marks: (obtained/total_possible_all_works) × subject_max_marks
            $aggregatedMarks = $totalPossibleMarks > 0 
                ? ($totalObtained / $totalPossibleMarks) * $subject->final_max_marks 
                : 0;
            
            $percentage = $totalPossibleMarks > 0 
                ? ($totalObtained / $totalPossibleMarks) * 100 
                : 0;

            return [
                'student_id' => $student->id,
                'student_name' => $student->user->name,
                'username' => $student->username,
                'total_obtained' => round($totalObtained, 2),
                'total_possible' => $totalPossibleMarks,
                'aggregated_marks' => round($aggregatedMarks, 2),
                'percentage' => round($percentage, 2)
            ];
        })->sortByDesc('aggregated_marks')->values();

        return response()->json([
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'max_marks' => $subject->final_max_marks,
                'class_id' => $subject->class_id,
                'class_name' => $subject->classRoom->name,
                'teacher_name' => $subject->teacher->name
            ],
            'total_works' => $totalWorks,
            'completed_works' => $completedWorks,
            'works' => $worksData,
            'student_marks' => $studentMarks
        ]);
    }
}
