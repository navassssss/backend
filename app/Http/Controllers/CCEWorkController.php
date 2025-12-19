<?php

namespace App\Http\Controllers;

use App\Models\CCEWork;
use App\Models\CCESubmission;
use App\Models\Student;
use Illuminate\Http\Request;

class CCEWorkController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = CCEWork::with(['subject.classRoom', 'subject.teacher']);

        // Role-based filtering
        if ($user->role === 'teacher') {
            // Teachers see only works for subjects they teach
            $query->whereHas('subject', function($q) use ($user) {
                $q->where('teacher_id', $user->id);
            });
        } elseif ($user->role !== 'principal' && $user->role !== 'manager') {
            // Non-principals/managers see nothing (shouldn't happen, but safe)
            return response()->json([]);
        }
        // Principals/managers see all works (no filter)

        // Additional filters from request
        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        $works = $query->orderBy('due_date', 'desc')
            ->get()
            ->map(function($work) {
                return [
                    'id' => $work->id,
                    'title' => $work->title,
                    'description' => $work->description,
                    'level' => $work->level,
                    'week' => $work->week,
                    'subjectId' => $work->subject_id,
                    'subjectName' => $work->subject->name,
                    'className' => $work->subject->classRoom->name,
                    'teacherName' => $work->subject->teacher->name,
                    'toolMethod' => $work->tool_method,
                    'issuedDate' => $work->issued_date->format('Y-m-d'),
                    'dueDate' => $work->due_date->format('Y-m-d'),
                    'maxMarks' => $work->max_marks,
                    'submissionType' => $work->submission_type,
                    'submissionsCount' => $work->submissions()->count(),
                    'evaluatedCount' => $work->submissions()->where('status', 'evaluated')->count()
                ];
            });

        return response()->json($works);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'level' => 'required|integer|min:1|max:4',
            'week' => 'required|integer|min:1|max:52',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tool_method' => 'nullable|string|max:255',
            'issued_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issued_date',
            'max_marks' => 'required|integer|min:1|max:100',
            'submission_type' => 'required|in:online,offline'
        ]);

        $validated['created_by'] = $request->user()->id;

        $work = CCEWork::create($validated);

        // Auto-create submissions for all students in the class
        $subject = $work->subject;
        $students = Student::where('class_id', $subject->class_id)->get();

        foreach ($students as $student) {
            CCESubmission::create([
                'work_id' => $work->id,
                'student_id' => $student->id,
                'status' => 'pending'
            ]);
        }

        return response()->json([
            'message' => 'CCE Work created successfully',
            'work' => $work
        ], 201);
    }

    public function show($id)
    {
        $work = CCEWork::with(['subject.classRoom', 'submissions.student.user'])->findOrFail($id);

        return response()->json([
            'id' => $work->id,
            'title' => $work->title,
            'description' => $work->description,
            'level' => $work->level,
            'week' => $work->week,
            'subjectName' => $work->subject->name,
            'className' => $work->subject->classRoom->name,
            'toolMethod' => $work->tool_method,
            'issuedDate' => $work->issued_date->format('Y-m-d'),
            'dueDate' => $work->due_date->format('Y-m-d'),
            'maxMarks' => $work->max_marks,
            'submissionType' => $work->submission_type,
            'submissions' => $work->submissions->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'studentId' => $sub->student_id,
                    'studentName' => $sub->student->user->name ?? 'Unknown',
                    'rollNumber' => $sub->student->roll_number,
                    'status' => $sub->status,
                    'submittedAt' => $sub->submitted_at?->toIso8601String(),
                    'marksObtained' => $sub->marks_obtained,
                    'feedback' => $sub->feedback,
                    'fileUrl' => $sub->file_url,
                ];
            })
        ]);
    }

    public function update(Request $request, $id)
    {
        $work = CCEWork::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'tool_method' => 'sometimes|nullable|string|max:255',
            'due_date' => 'sometimes|date',
            'max_marks' => 'sometimes|integer|min:1|max:100'
        ]);

        $work->update($validated);

        return response()->json([
            'message' => 'CCE Work updated successfully',
            'work' => $work
        ]);
    }

    public function destroy($id)
    {
        $work = CCEWork::findOrFail($id);
        $work->delete();

        return response()->json([
            'message' => 'CCE Work deleted successfully'
        ]);
    }
}
