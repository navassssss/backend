<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Student;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * Determine if the current user can create/edit/delete subjects.
     * Principal & manager: always yes.
     * Teacher: only if they have the manage_cce permission.
     */
    private function canManageSubjects(Request $request): bool
    {
        $user = $request->user();
        if ($user->role === 'principal') {
            return true;
        }
        if ($user->role === 'teacher') {
            return $user->permissions()->where('name', 'manage_cce')->exists();
        }
        return false;
    }

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

        if ($user->role === 'teacher') {
            $query->where('teacher_id', $user->id);
        }

        $subjects = $query->get()
            ->map(function ($subject) {
                $totalSubmissions    = $subject->works->sum('total_submissions');
                $evaluatedSubmissions = $subject->works->sum('evaluated_submissions');
                $completionPercent   = $totalSubmissions > 0
                    ? (int) round(($evaluatedSubmissions / $totalSubmissions) * 100) : 0;

                return [
                    'id'                 => $subject->id,
                    'name'               => $subject->name,
                    'code'               => $subject->code,
                    'className'          => $subject->classRoom->name,
                    'classId'            => $subject->class_id,
                    'teacherName'        => $subject->teacher->name,
                    'teacherId'          => $subject->teacher_id,
                    'finalMaxMarks'      => $subject->final_max_marks,
                    'isLocked'           => $subject->is_locked,
                    'assignmentScope'    => $subject->assignment_scope,
                    'completion_percent' => $completionPercent,
                ];
            });

        return response()->json($subjects);
    }

    public function bulkCreate(Request $request)
    {
        if (!$this->canManageSubjects($request)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage subjects.'], 403);
        }

        $validated = $request->validate([
            'subjects'                     => 'required|array',
            'subjects.*.name'              => 'required|string|max:255',
            'subjects.*.code'              => 'required|string|max:50',
            'subjects.*.class_id'          => 'required|exists:class_rooms,id',
            'subjects.*.teacher_id'        => 'required|exists:users,id',
            'subjects.*.final_max_marks'   => 'required|integer|min:1|max:100',
        ]);

        $createdSubjects = [];
        foreach ($validated['subjects'] as $subjectData) {
            // Bulk always full_class
            $subjectData['assignment_scope'] = 'full_class';
            $createdSubjects[] = Subject::create($subjectData);
        }

        return response()->json([
            'message' => 'Successfully processed ' . count($createdSubjects) . ' subjects',
            'data'    => $createdSubjects,
        ], 201);
    }

    public function store(Request $request)
    {
        if (!$this->canManageSubjects($request)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage subjects.'], 403);
        }

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'code'             => 'required|string|max:50',
            'class_id'         => 'required|exists:class_rooms,id',
            'teacher_id'       => 'required|exists:users,id',
            'final_max_marks'  => 'required|integer|min:1|max:100',
            'assignment_scope' => 'sometimes|in:full_class,selected_students',
            'student_ids'      => 'sometimes|array',
            'student_ids.*'    => 'exists:students,id',
            'code'             => [
                'required', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('subjects')->where('class_id', $request->class_id),
            ],
        ], [
            'name.required'        => 'Please enter a subject name.',
            'code.required'        => 'Please enter a subject code.',
            'code.unique'          => 'A subject with this code already exists in the selected class.',
            'class_id.required'    => 'Please select a class.',
            'class_id.exists'      => 'The selected class does not exist.',
            'teacher_id.required'  => 'Please select a teacher.',
            'teacher_id.exists'    => 'The selected teacher does not exist.',
            'final_max_marks.required' => 'Please enter the maximum marks.',
            'final_max_marks.min'  => 'Maximum marks must be at least 1.',
            'final_max_marks.max'  => 'Maximum marks cannot exceed 100.',
        ]);


        $scope = $validated['assignment_scope'] ?? 'full_class';
        $studentIds = $validated['student_ids'] ?? [];
        unset($validated['student_ids']);

        $subject = Subject::create($validated);

        if ($scope === 'selected_students' && !empty($studentIds)) {
            $subject->assignedStudents()->sync($studentIds);
        }

        return response()->json([
            'message' => 'Subject created successfully',
            'subject' => $subject,
        ], 201);
    }

    public function show($id)
    {
        $subject = Subject::with(['classRoom', 'teacher', 'works', 'assignedStudents'])->findOrFail($id);

        return response()->json([
            'id'              => $subject->id,
            'name'            => $subject->name,
            'code'            => $subject->code,
            'className'       => $subject->classRoom->name,
            'classId'         => $subject->class_id,
            'teacherName'     => $subject->teacher->name,
            'teacherId'       => $subject->teacher_id,
            'finalMaxMarks'   => $subject->final_max_marks,
            'isLocked'        => $subject->is_locked,
            'assignmentScope' => $subject->assignment_scope,
            'studentIds'      => $subject->assignedStudents->pluck('id'),
            'worksCount'      => $subject->works->count(),
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!$this->canManageSubjects($request)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage subjects.'], 403);
        }

        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'code'             => [
                'sometimes', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('subjects')
                    ->where('class_id', $request->class_id ?? $subject->class_id)
                    ->ignore($id),
            ],
            'teacher_id'       => 'sometimes|exists:users,id',
            'final_max_marks'  => 'sometimes|integer|min:1|max:100',
            'assignment_scope' => 'sometimes|in:full_class,selected_students',
            'student_ids'      => 'sometimes|array',
            'student_ids.*'    => 'exists:students,id',
        ], [
            'code.unique' => 'A subject with this code already exists in the selected class.',
        ]);

        $studentIds = $validated['student_ids'] ?? null;
        unset($validated['student_ids']);

        $subject->update($validated);

        // Sync pivot if scope is selected_students (or if student_ids explicitly passed)
        if (($subject->assignment_scope === 'selected_students') && $studentIds !== null) {
            $subject->assignedStudents()->sync($studentIds);
        } elseif (isset($validated['assignment_scope']) && $validated['assignment_scope'] === 'full_class') {
            // Switching back to full_class — clear pivot
            $subject->assignedStudents()->detach();
        }

        return response()->json([
            'message' => 'Subject updated successfully',
            'subject' => $subject,
        ]);
    }

    public function toggleLock(Request $request, $id)
    {
        if (!$this->canManageSubjects($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $subject = Subject::findOrFail($id);
        $subject->is_locked = !$subject->is_locked;
        $subject->save();

        return response()->json([
            'message'  => 'Subject lock status updated',
            'isLocked' => $subject->is_locked,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if (!$this->canManageSubjects($request)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage subjects.'], 403);
        }

        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json(['message' => 'Subject deleted successfully']);
    }

    public function getSubjectStatistics($id)
    {
        $subject = Subject::with(['classRoom', 'teacher'])->findOrFail($id);

        // Effective student IDs respects assignment_scope
        $studentIds = $subject->effectiveStudentIds();
        $totalStudents = $studentIds->count();

        $works = \App\Models\CCEWork::where('subject_id', $id)
            ->withCount([
                'submissions as evaluated_count' => fn($q) => $q->whereNotNull('marks_obtained'),
            ])
            ->orderBy('deadline', 'desc')
            ->get();

        $totalWorks     = $works->count();
        $completedWorks = 0;
        $now            = now();

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

        $obtainedMap = \DB::table('cce_submissions as s')
            ->join('cce_works as w', 's.work_id', '=', 'w.id')
            ->select('s.student_id', \DB::raw('SUM(s.marks_obtained) as total_obtained'))
            ->where('w.subject_id', $id)
            ->whereNotNull('s.marks_obtained')
            ->whereIn('s.student_id', $studentIds)
            ->groupBy('s.student_id')
            ->pluck('total_obtained', 'student_id');

        $students = \App\Models\Student::whereIn('id', $studentIds)
            ->with('user:id,name')
            ->select('id', 'user_id', 'username')
            ->get();

        $studentMarks = $students->map(function ($student) use ($obtainedMap, $totalPossibleMarks, $subject) {
            $totalObtained   = (float) ($obtainedMap[$student->id] ?? 0);
            $aggregatedMarks = $totalPossibleMarks > 0
                ? ($totalObtained / $totalPossibleMarks) * $subject->final_max_marks : 0;
            $percentage = $totalPossibleMarks > 0
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
            'total_works'     => $totalWorks,
            'completed_works' => $completedWorks,
            'works'           => $worksData,
            'student_marks'   => $studentMarks,
        ]);
    }
}
