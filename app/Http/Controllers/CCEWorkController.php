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

        // Get subject summaries for the user
        $subjectsQuery = \App\Models\Subject::with(['classRoom']);
        
        if ($user->role === 'teacher') {
            $subjectsQuery->where('teacher_id', $user->id);
        }
        
        $subjects = $subjectsQuery->get();
        
        $subjectsSummary = $subjects->map(function($subject) {
            $allWorks = CCEWork::where('subject_id', $subject->id)
                ->with(['submissions' => function($query) {
                    $query->whereNotNull('marks');
                }])
                ->get();
            
            $totalWorks = $allWorks->count();
            $completedWorks = 0;
            
            foreach ($allWorks as $work) {
                $now = now();
                $deadlinePassed = $work->deadline ? $now->gt($work->deadline) : false;
                $evaluatedCount = $work->submissions->count();
                
                if ($deadlinePassed && $evaluatedCount > 0) {
                    $completedWorks++;
                }
            }
            
            return [
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'max_marks' => $subject->final_max_marks,
                'class_name' => $subject->classRoom->name,
                'total_works' => $totalWorks,
                'completed_works' => $completedWorks
            ];
        });

        return response()->json([
            'works' => $works,
            'subjects_summary' => $subjectsSummary
        ]);
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

    public function getStudentMarks(Request $request)
    {
        $classId = $request->query('class_id');
        $search = $request->query('search');
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);
        
        // TEMPORARY DEBUG - Always show what we receive
        return response()->json([
            'debug' => true,
            'message' => 'Debug: All parameters',
            'search_value' => $search,
            'search_is_null' => is_null($search),
            'search_is_empty' => empty($search),
            'class_id' => $classId,
            'page' => $page,
            'per_page' => $perPage,
            'all_query_params' => $request->query(),
            'timestamp' => now()->toDateTimeString()
        ]);
        
        // Get students based on class filter and search
        $studentsQuery = Student::with(['user', 'class']);
        if ($classId) {
            $studentsQuery->where('class_id', $classId);
        }
        if ($search) {
            $studentsQuery->where(function($query) use ($search) {
                // Search in user name
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })
                // OR search in student username (roll number)
                ->orWhere('username', 'like', '%' . $search . '%');
            });
        }
        $allStudents = $studentsQuery->get();
        
        // Debug logging
        \Log::info('CCE Student Marks - Search Debug', [
            'search_param' => $search,
            'class_id' => $classId,
            'total_students_found' => $allStudents->count()
        ]);

        // Get all subjects
        $subjects = \App\Models\Subject::all();

        // Calculate marks for all students first
        $allStudentMarks = $allStudents->map(function($student) use ($subjects) {
            $subjectMarks = [];
            $totalObtained = 0;
            $totalMarks = 0;

            foreach ($subjects as $subject) {
                // Get all works for this subject
                $works = CCEWork::where('subject_id', $subject->id)->get();
                $subjectTotalMarks = $works->sum('max_marks');
                
                // Get student's evaluated submissions for this subject
                $submissions = CCESubmission::whereHas('work', function($query) use ($subject) {
                    $query->where('subject_id', $subject->id);
                })
                ->where('student_id', $student->id)
                ->whereNotNull('marks_obtained')
                ->with('work')
                ->get();

                $subjectObtained = $submissions->sum('marks_obtained');
                
                // Only include subjects where student has at least one evaluation
                if ($submissions->count() > 0) {
                    // Convert to subject's final_max_marks if set
                    $finalMaxMarks = $subject->final_max_marks ?? $subjectTotalMarks;
                    $convertedObtained = $subjectTotalMarks > 0 
                        ? ($subjectObtained / $subjectTotalMarks) * $finalMaxMarks 
                        : 0;
                    
                    $percentage = $finalMaxMarks > 0 
                        ? ($convertedObtained / $finalMaxMarks) * 100 
                        : 0;

                    $subjectMarks[$subject->name] = [
                        'subjectName' => $subject->name,
                        'obtained' => round($convertedObtained, 2),
                        'total' => $finalMaxMarks,
                        'percentage' => round($percentage, 2)
                    ];

                    $totalObtained += $convertedObtained;
                    $totalMarks += $finalMaxMarks;
                }
            }

            // Only include students who have at least one subject evaluation
            if (count($subjectMarks) > 0) {
                $overallPercentage = $totalMarks > 0 
                    ? ($totalObtained / $totalMarks) * 100 
                    : 0;

                return [
                    'studentId' => $student->id,
                    'studentName' => $student->user->name,
                    'rollNumber' => $student->username,
                    'className' => $student->class->name ?? 'N/A',
                    'subjectMarks' => $subjectMarks,
                    'totalObtained' => round($totalObtained, 2),
                    'totalMarks' => $totalMarks,
                    'overallPercentage' => round($overallPercentage, 2)
                ];
            }

            return null;
        })->filter()->values();
        
        \Log::info('CCE Student Marks - After filtering', [
            'students_with_marks' => $allStudentMarks->count()
        ]);

        // Apply pagination
        $total = $allStudentMarks->count();
        $studentMarks = $allStudentMarks->slice(($page - 1) * $perPage, $perPage)->values();

        // Calculate total stats from all filtered results
        $allSubjects = [];
        $totalPercentageSum = 0;
        foreach ($allStudentMarks as $student) {
            foreach ($student['subjectMarks'] as $subjectName => $marks) {
                $allSubjects[$subjectName] = true;
            }
            $totalPercentageSum += $student['overallPercentage'];
        }

        return response()->json([
            'data' => $studentMarks,
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'stats' => [
                'total_students' => $total,
                'total_subjects' => count($allSubjects),
                'average_percentage' => $total > 0 ? round($totalPercentageSum / $total, 2) : 0
            ]
        ]);
    }

    public function getClassReport(Request $request)
    {
        $classId = $request->query('class_id');
        
        if (!$classId) {
            return response()->json(['error' => 'class_id is required'], 400);
        }

        // Get class details
        $class = \App\Models\ClassRoom::findOrFail($classId);

        // Get all subjects for this class
        $subjects = \App\Models\Subject::where('class_id', $classId)
            ->with(['works' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->get();

        // Get all students in this class
        $students = Student::where('class_id', $classId)
            ->with(['user'])
            ->orderBy('username', 'asc')
            ->get();

        // Build works array grouped by subject
        $subjectsData = $subjects->map(function($subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'max_marks' => $subject->final_max_marks,
                'works' => $subject->works->map(function($work) {
                    return [
                        'id' => $work->id,
                        'title' => $work->title,
                        'max_marks' => $work->max_marks,
                    ];
                })->toArray()
            ];
        })->toArray();

        // Get all submissions for this class
        $allSubmissions = CCESubmission::whereHas('work.subject', function($query) use ($classId) {
            $query->where('class_id', $classId);
        })
        ->whereNotNull('marks_obtained')
        ->get()
        ->groupBy('student_id');

        // Build student data
        $studentsData = $students->map(function($student) use ($subjects, $allSubmissions) {
            $studentSubmissions = $allSubmissions->get($student->id, collect());
            
            // Create marks map: work_id => marks_obtained
            $marksMap = [];
            foreach ($studentSubmissions as $submission) {
                $marksMap[$submission->work_id] = $submission->marks_obtained;
            }

            // Calculate subject totals
            $subjectTotals = [];
            $overallObtained = 0;
            $overallTotal = 0;

            foreach ($subjects as $subject) {
                $subjectObtained = 0;
                $subjectTotal = 0;

                foreach ($subject->works as $work) {
                    $subjectTotal += $work->max_marks;
                    if (isset($marksMap[$work->id])) {
                        $subjectObtained += $marksMap[$work->id];
                    }
                }

                // Convert to subject's final_max_marks if set
                $finalMaxMarks = $subject->final_max_marks ?? $subjectTotal;
                $convertedObtained = $subjectTotal > 0 
                    ? ($subjectObtained / $subjectTotal) * $finalMaxMarks 
                    : 0;

                if ($finalMaxMarks > 0) {
                    $subjectTotals[$subject->id] = [
                        'obtained' => round($convertedObtained, 2),
                        'total' => $finalMaxMarks,
                        'percentage' => ($convertedObtained / $finalMaxMarks) * 100
                    ];
                    $overallObtained += $convertedObtained;
                    $overallTotal += $finalMaxMarks;
                }
            }

            $overallPercentage = $overallTotal > 0 ? ($overallObtained / $overallTotal) * 100 : 0;

            return [
                'id' => $student->id,
                'name' => $student->user->name,
                'roll_number' => $student->username,
                'marks' => $marksMap,
                'subject_totals' => $subjectTotals,
                'overall_obtained' => round($overallObtained, 2),
                'overall_total' => $overallTotal,
                'overall_percentage' => round($overallPercentage, 2)
            ];
        })->toArray();

        return response()->json([
            'class' => [
                'id' => $class->id,
                'name' => $class->name
            ],
            'subjects' => $subjectsData,
            'students' => $studentsData
        ]);
    }
}
