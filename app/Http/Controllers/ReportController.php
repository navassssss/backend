<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Report::with([
            'teacher:id,name',
            'task.duty:id,name',
            'attachments',
        ]);

        if ($user->role !== 'principal') {
            $query->where('teacher_id', $user->id);
        }

        if ($request->status) {
            $query->when($request->status === 'pending', fn ($q) => $q->where('status', 'submitted')
            );
            $query->when($request->status === 'reviewed', fn ($q) => $q->whereIn('status', ['approved', 'rejected'])
            );
        }

        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'task_id' => 'nullable|exists:tasks,id',
            'duty_id' => 'nullable|exists:duties,id',
            'description' => 'required|string|min:3',
            'attachments.*' => 'nullable|file|max:5120',
        ]);

        // Ensure either task_id or duty_id is provided
        if (!$request->task_id && !$request->duty_id) {
            return response()->json([
                'message' => 'Either task_id or duty_id is required'
            ], 422);
        }

        // If duty_id provided, auto-create a task
        if ($request->duty_id && !$request->task_id) {
            $duty = \App\Models\Duty::findOrFail($request->duty_id);
            $task = Task::create([
                'duty_id' => $request->duty_id,
                'assigned_to' => Auth::id(),
                'title' => 'Report: ' . $duty->name,
                'instructions' => 'Auto-generated task for duty report submission',
                'scheduled_date' => now()->toDateString(),
                'scheduled_time' => now()->toTimeString(),
                'status' => 'completed',
            ]);
            $taskId = $task->id;
        } else {
            $taskId = $request->task_id;
            $task = Task::findOrFail($taskId);
            $task->update(['status' => 'completed']);
        }

        $latestPrevious = Report::where('task_id', $taskId)
            ->latest()
            ->first();

        $report = Report::create([
            'task_id' => $taskId,
            'parent_report_id' => $latestPrevious?->id,
            'teacher_id' => Auth::id(),
            'description' => $request->description,
            'status' => 'submitted',
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('report_attachments', 'public');
                $ext = strtolower($file->getClientOriginalExtension());

                $report->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])
                        ? 'image'
                        : 'document',
                ]);
            }
        }

        return response()->json([
            'message' => 'Report submitted successfully!',
            'report' => $report->load(['task.duty', 'attachments']),
        ], 201);
    }

    public function show(Report $report)
    {
        // Load current report
        $report->load([
            'teacher:id,name,role',
            'task.duty:id,name',
            'attachments',
            'comments.user:id,name',
        ]);

        // Load history of same task excluding current one
        $history = Report::where('task_id', $report->task_id)
            ->where('id', '!=', $report->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'status', 'created_at', 'description']);

        return response()->json([
            'report' => $report,
            'history' => $history,
        ]);
    }

    public function approve(Report $report)
    {
        $report->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'review_note' => null,
        ]);

        $report->load('teacher');
        if ($report->teacher) {
            $report->teacher->notify(new \App\Notifications\ReportReviewed($report, Auth::user()));
        }

        return response()->json([
            'message' => 'Report approved',
            'report' => $report->load(['attachments', 'task.duty', 'comments.user']),
        ]);
    }

    public function reject(Request $request, Report $report)
    {
        $request->validate(['review_note' => 'required|string']);

        $report->update([
            'status' => 'rejected',
            'review_note' => $request->review_note,
            'reviewed_by' => Auth::id(),
        ]);

        $report->load('teacher');
        if ($report->teacher) {
            $report->teacher->notify(new \App\Notifications\ReportReviewed($report, Auth::user()));
        }

        $report->task->update(['status' => 'pending']);

        $report->comments()->create([
            'user_id' => Auth::id(),
            'comment' => $request->review_note,
        ]);

        return response()->json([
            'message' => 'Report rejected',
            'report' => $report->load(['attachments', 'task.duty', 'comments.user']),
        ]);
    }

    public function addComment(Request $request, Report $report)
    {
        $request->validate(['comment' => 'required|string']);

        $comment = $report->comments()->create([
            'user_id' => Auth::id(),
            'comment' => $request->comment,
        ]);

        return response()->json($comment);
    }

    public function reportsByTask(Task $task)
    {
        return $task->reports()
            ->with(['teacher:id,name', 'comments:user:id,name'])
            ->latest()
            ->get();
    }
}
