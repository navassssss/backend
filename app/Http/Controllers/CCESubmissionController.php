<?php

namespace App\Http\Controllers;

use App\Models\CCESubmission;
use App\Models\CCEWork;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CCESubmissionController extends Controller
{
    public function evaluate(Request $request, $id)
    {
        $submission = CCESubmission::findOrFail($id);

        $validated = $request->validate([
            'marks_obtained' => 'required|numeric|min:0|max:' . $submission->work->max_marks,
            'feedback' => 'nullable|string'
        ]);

        $submission->update([
            'marks_obtained' => $validated['marks_obtained'],
            'feedback' => $validated['feedback'] ?? null,
            'evaluated_by' => $request->user()->id,
            'evaluated_at' => now(),
            'status' => 'evaluated'
        ]);

        return response()->json([
            'message' => 'Submission evaluated successfully',
            'submission' => $submission
        ]);
    }

    // Student endpoints
    public function studentWorks(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $submissions = CCESubmission::where('student_id', $student->id)
            ->with(['work.subject'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'workId' => $sub->work_id,
                    'title' => $sub->work->title,
                    'subjectName' => $sub->work->subject->name,
                    'subjectId' => $sub->work->subject_id,
                    'level' => $sub->work->level,
                    'dueDate' => $sub->work->due_date->format('Y-m-d'),
                    'maxMarks' => $sub->work->max_marks,
                    'submissionType' => $sub->work->submission_type,
                    'status' => $sub->status,
                    'submittedAt' => $sub->submitted_at?->toIso8601String(),
                    'marksObtained' => $sub->marks_obtained,
                    'feedback' => $sub->feedback,
                    'fileUrl' => $sub->file_url
                ];
            });

        // Calculate subject-wise aggregation
        $subjectMarks = CCESubmission::where('student_id', $student->id)
            ->where('status', 'evaluated')
            ->with('work.subject')
            ->get()
            ->groupBy(function($sub) {
                return $sub->work->subject_id;
            })
            ->map(function($subs, $subjectId) {
                $subject = $subs->first()->work->subject;
                $obtained = $subs->sum('marks_obtained');
                $total = $subs->sum(function($sub) {
                    return $sub->work->max_marks;
                });
                
                return [
                    'subjectId' => $subjectId,
                    'subjectName' => $subject->name,
                    'marksObtained' => $obtained,
                    'totalMarks' => $total,
                    'percentage' => $total > 0 ? round(($obtained / $total) * 100, 2) : 0
                ];
            })->values();

        return response()->json([
            'submissions' => $submissions,
            'subjectMarks' => $subjectMarks
        ]);
    }

    public function submitWork(Request $request, $id)
    {
        $submission = CCESubmission::findOrFail($id);
        $student = $request->user()->student;

        if ($submission->student_id !== $student->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,zip|max:10240' // 10MB
        ]);

        $fileUrl = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = $submission->work_id . '_' . $student->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('cce_submissions', $filename, 'public');
            $fileUrl = Storage::url($path);
        }

        $submission->update([
            'submitted_at' => now(),
            'file_url' => $fileUrl ?? $submission->file_url,
            'status' => 'submitted'
        ]);

        return response()->json([
            'message' => 'Work submitted successfully',
            'submission' => $submission,
            'fileUrl' => $fileUrl
        ]);
    }

    // Principal endpoint - Student marks overview (SQL aggregation, no N+1)
    public function studentMarks(Request $request)
    {
        $classId   = $request->query('class_id');
        $studentId = $request->query('student_id');
        $search    = $request->query('search');
        $page      = (int) $request->query('page', 1);
        $perPage   = (int) $request->query('per_page', 10);

        $query = Student::with(['user:id,name', 'classRoom:id,name'])
            ->select('students.id', 'students.user_id', 'students.class_id', 'students.roll_number', 'students.username');

        if ($classId)   $query->where('class_id', $classId);
        if ($studentId) $query->where('id', $studentId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                  ->orWhere('roll_number', 'like', "%{$search}%");
            });
        }

        $studentIds = $query->pluck('students.id');

        if ($studentIds->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => $page, 'per_page' => $perPage, 'last_page' => 1, 'stats' => ['total_students' => 0, 'total_subjects' => 0, 'average_percentage' => 0]]);
        }

        // Single aggregation query — replaces N per-student DB calls
        $aggregates = DB::table('cce_submissions as s')
            ->join('cce_works as w', 's.work_id', '=', 'w.id')
            ->join('subjects as sub', 'w.subject_id', '=', 'sub.id')
            ->select(
                's.student_id',
                'sub.id as subject_id',
                'sub.name as subject_name',
                'sub.final_max_marks',
                DB::raw('SUM(s.marks_obtained) as obtained'),
                DB::raw('SUM(w.max_marks) as raw_max')
            )
            ->whereIn('s.student_id', $studentIds)
            ->where('s.status', 'evaluated')
            ->whereNotNull('s.marks_obtained')
            ->groupBy('s.student_id', 'sub.id', 'sub.name', 'sub.final_max_marks')
            ->get()
            ->groupBy('student_id');

        $students = $query->get()->keyBy('id');

        $allStudentMarks = $studentIds->map(function ($sid) use ($students, $aggregates) {
            $student     = $students[$sid];
            $subjectRows = $aggregates->get($sid, collect());

            $subjectMarks  = [];
            $totalObtained = 0;
            $totalMarks    = 0;

            foreach ($subjectRows as $row) {
                $subjectMarks[$row->subject_name] = [
                    'subjectName' => $row->subject_name,
                    'obtained'    => round((float) $row->obtained, 2),
                    'total'       => (float) ($row->final_max_marks ?? $row->raw_max),
                    'percentage'  => $row->raw_max > 0
                        ? round(($row->obtained / $row->raw_max) * 100, 2) : 0,
                ];
                $totalObtained += $row->obtained;
                $totalMarks    += $row->final_max_marks ?? $row->raw_max;
            }

            return [
                'studentId'         => $student->id,
                'studentName'       => $student->user->name ?? 'Unknown',
                'rollNumber'        => $student->roll_number,
                'className'         => $student->classRoom->name ?? 'N/A',
                'subjectMarks'      => $subjectMarks,
                'totalObtained'     => round($totalObtained, 2),
                'totalMarks'        => $totalMarks,
                'overallPercentage' => $totalMarks > 0 ? round(($totalObtained / $totalMarks) * 100, 2) : 0,
            ];
        })->values();

        $total          = $allStudentMarks->count();
        $paginatedData  = $allStudentMarks->slice(($page - 1) * $perPage, $perPage)->values();
        $allSubjectNames = collect($aggregates->flatten(1))->pluck('subject_name')->unique()->count();

        return response()->json([
            'data'         => $paginatedData,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
            'per_page'     => $perPage,
            'total'        => $total,
            'stats'        => [
                'total_students'     => $total,
                'total_subjects'     => $allSubjectNames,
                'average_percentage' => $total > 0 ? round($allStudentMarks->avg('overallPercentage'), 2) : 0,
            ],
        ]);
    }
}
