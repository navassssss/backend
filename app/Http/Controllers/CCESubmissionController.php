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

    // Principal endpoint - Student marks overview
    public function studentMarks(Request $request)
    {
        $classId = $request->query('class_id');
        
        $query = Student::with(['user', 'classRoom']);
        
        if ($classId) {
            $query->where('class_id', $classId);
        }
        
        $students = $query->get()->map(function($student) {
            $submissions = CCESubmission::where('student_id', $student->id)
                ->where('status', 'evaluated')
                ->with('work.subject')
                ->get();
            
            $subjectMarks = $submissions->groupBy(function($sub) {
                return $sub->work->subject_id;
            })->map(function($subs, $subjectId) {
                $subject = $subs->first()->work->subject;
                $obtained = $subs->sum('marks_obtained');
                $total = $subs->sum(function($sub) {
                    return $sub->work->max_marks;
                });
                
                return [
                    'subjectName' => $subject->name,
                    'obtained' => $obtained,
                    'total' => $total,
                    'percentage' => $total > 0 ? round(($obtained / $total) * 100, 2) : 0
                ];
            });
            
            $totalObtained = $submissions->sum('marks_obtained');
            $totalMarks = $submissions->sum(function($sub) {
                return $sub->work->max_marks;
            });
            
            return [
                'studentId' => $student->id,
                'studentName' => $student->user->name ?? 'Unknown',
                'rollNumber' => $student->roll_number,
                'className' => $student->classRoom->name,
                'subjectMarks' => $subjectMarks,
                'totalObtained' => $totalObtained,
                'totalMarks' => $totalMarks,
                'overallPercentage' => $totalMarks > 0 ? round(($totalObtained / $totalMarks) * 100, 2) : 0
            ];
        });
        
        return response()->json($students);
    }
}
