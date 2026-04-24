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
            ->withCount([
                'submissions as submissions_count',
                'submissions as evaluated_count' => fn($q) => $q->where('status', 'evaluated'),
            ])
            ->get()
            ->map(function ($work) {
                return [
                    'id'              => $work->id,
                    'title'           => $work->title,
                    'description'     => $work->description,
                    'level'           => $work->level,
                    'week'            => $work->week,
                    'subjectId'       => $work->subject_id,
                    'subjectName'     => $work->subject->name,
                    'className'       => $work->subject->classRoom->name,
                    'teacherName'     => $work->subject->teacher->name,
                    'toolMethod'      => $work->tool_method,
                    'issuedDate'      => $work->issued_date->format('Y-m-d'),
                    'dueDate'         => $work->due_date->format('Y-m-d'),
                    'maxMarks'        => $work->max_marks,
                    'submissionType'  => $work->submission_type,
                    'submissionsCount' => $work->submissions_count,
                    'evaluatedCount'  => $work->evaluated_count,
                ];
            });

        // Get subject summaries for the user
        $subjectsQuery = \App\Models\Subject::with(['classRoom']);
        
        if ($user->role === 'teacher') {
            $subjectsQuery->where('teacher_id', $user->id);
        }
        
        $subjects = $subjectsQuery->get();
        
        // Preload all work counts for subjects in ONE query per aggregation
        $subjectIds = $subjects->pluck('id');
        $workCountMap = CCEWork::selectRaw('subject_id, COUNT(*) as total, SUM(CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) as past_deadline', [now()])
            ->whereIn('subject_id', $subjectIds)
            ->groupBy('subject_id')
            ->get()
            ->keyBy('subject_id');

        $evalCountMap = CCEWork::selectRaw('cce_works.subject_id, COUNT(DISTINCT cce_submissions.work_id) as eval_count')
            ->join('cce_submissions', 'cce_works.id', '=', 'cce_submissions.work_id')
            ->whereIn('cce_works.subject_id', $subjectIds)
            ->whereNotNull('cce_submissions.marks_obtained')
            ->groupBy('cce_works.subject_id')
            ->pluck('eval_count', 'subject_id');

        $subjectsSummary = $subjects->map(function ($subject) use ($workCountMap, $evalCountMap) {
            $data = $workCountMap[$subject->id] ?? null;
            $totalWorks    = $data?->total ?? 0;
            $completedWorks = $data?->past_deadline > 0 ? ($evalCountMap[$subject->id] ?? 0) : 0;

            return [
                'subject_id'      => $subject->id,
                'subject_name'    => $subject->name,
                'max_marks'       => $subject->final_max_marks,
                'class_name'      => $subject->classRoom->name,
                'total_works'     => $totalWorks,
                'completed_works' => $completedWorks,
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
        $studentIds = Student::where('class_id', $subject->class_id)->pluck('id');
        $now = now();

        if ($studentIds->isNotEmpty()) {
            $submissions = $studentIds->map(fn($id) => [
                'work_id'    => $work->id,
                'student_id' => $id,
                'status'     => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            CCESubmission::insert($submissions);
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
                    'email' => $sub->student->user->email ?? 'no-email@academy.edu',
                    'rollNumber' => $sub->student->roll_number ?? $sub->student->username,
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
        $search  = $request->query('search');
        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);

        // Build student base query
        $studentsQuery = Student::with(['user:id,name', 'class:id,name'])
            ->select('students.id', 'students.user_id', 'students.class_id', 'students.username');

        if ($classId) {
            $studentsQuery->where('class_id', $classId);
        }
        if ($search) {
            $studentsQuery->where(function ($q) use ($search) {
                $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $studentIds = $studentsQuery->pluck('students.id');

        if ($studentIds->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => $page, 'per_page' => $perPage, 'last_page' => 1, 'stats' => ['total_students' => 0, 'total_subjects' => 0, 'average_percentage' => 0]]);
        }

        // ── Single aggregation query for all students × all subjects ──────────
        $aggregates = \DB::table('cce_submissions as s')
            ->join('cce_works as w', 's.work_id', '=', 'w.id')
            ->join('subjects as sub', 'w.subject_id', '=', 'sub.id')
            ->select(
                's.student_id',
                'sub.id as subject_id',
                'sub.name as subject_name',
                'sub.final_max_marks',
                \DB::raw('SUM(s.marks_obtained) as obtained'),
                \DB::raw('SUM(w.max_marks) as raw_max')
            )
            ->whereIn('s.student_id', $studentIds)
            ->whereNotNull('s.marks_obtained')
            ->where('s.status', 'evaluated')
            ->groupBy('s.student_id', 'sub.id', 'sub.name', 'sub.final_max_marks')
            ->get()
            ->groupBy('student_id');

        $students = $studentsQuery->get()->keyBy('id');

        // Build per-student result in pure PHP (no more DB calls in loop)
        $allStudentMarks = $studentIds
            ->map(function ($sid) use ($students, $aggregates) {
                $student   = $students[$sid];
                $subjectRows = $aggregates->get($sid, collect());

                if ($subjectRows->isEmpty()) return null;

                $subjectMarks  = [];
                $totalObtained = 0;
                $totalMarks    = 0;

                foreach ($subjectRows as $row) {
                    $finalMax  = (float) ($row->final_max_marks ?? $row->raw_max);
                    $converted = $row->raw_max > 0
                        ? ($row->obtained / $row->raw_max) * $finalMax
                        : 0;
                    $pct = $finalMax > 0 ? ($converted / $finalMax) * 100 : 0;

                    $subjectMarks[$row->subject_name] = [
                        'subjectName' => $row->subject_name,
                        'obtained'    => round($converted, 2),
                        'total'       => $finalMax,
                        'percentage'  => round($pct, 2),
                    ];

                    $totalObtained += $converted;
                    $totalMarks    += $finalMax;
                }

                $overallPct = $totalMarks > 0 ? ($totalObtained / $totalMarks) * 100 : 0;

                return [
                    'studentId'         => $student->id,
                    'studentName'       => $student->user->name ?? 'Unknown',
                    'rollNumber'        => $student->username,
                    'className'         => $student->class->name ?? 'N/A',
                    'subjectMarks'      => $subjectMarks,
                    'totalObtained'     => round($totalObtained, 2),
                    'totalMarks'        => $totalMarks,
                    'overallPercentage' => round($overallPct, 2),
                ];
            })
            ->filter()
            ->values();

        // Paginate in PHP (data already in memory)
        $total        = $allStudentMarks->count();
        $studentMarks = $allStudentMarks->slice(($page - 1) * $perPage, $perPage)->values();

        $totalSubjects      = collect($aggregates->flatten(1))->pluck('subject_name')->unique()->count();
        $avgPct             = $total > 0 ? round($allStudentMarks->avg('overallPercentage'), 2) : 0;

        return response()->json([
            'data'         => $studentMarks,
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
            'stats'        => [
                'total_students'     => $total,
                'total_subjects'     => $totalSubjects,
                'average_percentage' => $avgPct,
            ],
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
