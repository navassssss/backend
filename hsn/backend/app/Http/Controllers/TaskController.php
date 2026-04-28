<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{


    public function index(Request $request)
    {
        $user  = Auth::user();
        $limit = max(1, min(100, (int) $request->query('per_page', 50)));

        $query = Task::with('duty:id,name', 'assignedTo:id,name,role');

        if ($request->has('teacher_id')) {
            return $query->where('assigned_to', $request->teacher_id)
                         ->latest('scheduled_date')
                         ->paginate($limit);
        }

        if ($user->role === 'principal') {
            return $query->latest('scheduled_date')->paginate($limit);
        }

        return $query->where('assigned_to', $user->id)
                     ->latest('scheduled_date')
                     ->paginate($limit);
    }

    /**
     * Create scheduled task(s) — Principal Only
     *
     * Supports two modes:
     *   1. Duty-based (duty_assignments): auto-generates title per duty as
     *      "{Duty Name} Report" or "{Duty Name} - {custom_title}" when custom_title given.
     *   2. Manual (teacher_ids + title): flat list of teachers with a single title.
     */
    public function store(Request $request)
    {
        // ── Duty-based bulk assignment ──────────────────────────────
        if ($request->has('duty_assignments')) {
            $request->validate([
                'duty_assignments'               => 'required|array|min:1',
                'duty_assignments.*.duty_id'     => 'required|exists:duties,id',
                'duty_assignments.*.teacher_ids' => 'required|array',
                'duty_assignments.*.teacher_ids.*' => 'exists:users,id',
                'custom_title'                   => 'nullable|string|max:255',
                'scheduled_date'                 => 'required|date',
                'scheduled_time'                 => 'nullable|string',
                'instructions'                   => 'nullable|string',
            ]);

            // Collect all unique teacher IDs for a single batch query
            $allTeacherIds = collect($request->duty_assignments)
                ->flatMap(fn ($a) => $a['teacher_ids'])
                ->unique()
                ->values()
                ->all();

            $teachers  = \App\Models\User::whereIn('id', $allTeacherIds)->get()->keyBy('id');
            $duties    = \App\Models\Duty::whereIn('id',
                            collect($request->duty_assignments)->pluck('duty_id')->all()
                         )->get()->keyBy('id');

            $createdTasks = [];

            foreach ($request->duty_assignments as $assignment) {
                $duty = $duties->get($assignment['duty_id']);
                if (! $duty) continue;

                // Title: "Play Report" or "Play - Custom Title"
                $title = trim($duty->name) . ' Report';
                if (! empty($request->custom_title)) {
                    $title = trim($duty->name) . ' - ' . trim($request->custom_title);
                }

                foreach ($assignment['teacher_ids'] as $teacherId) {
                    $task = Task::create([
                        'title'          => $title,
                        'duty_id'        => $duty->id,
                        'assigned_to'    => $teacherId,
                        'scheduled_date' => $request->scheduled_date,
                        'scheduled_time' => $request->scheduled_time,
                        'instructions'   => $request->instructions,
                        'status'         => 'pending',
                    ]);

                    $assignedUser = $teachers->get($teacherId);
                    if ($assignedUser) {
                        $assignedUser->notify(new \App\Notifications\TaskAssigned($task, Auth::user()));

                        \App\Jobs\SendPushNotificationJob::dispatch($teacherId, [
                            'title' => 'New Task Assigned',
                            'body'  => Auth::user()->name . ' assigned you: ' . $task->title,
                            'url'   => '/tasks/' . $task->id,
                            'tag'   => 'task-assigned-' . $task->id,
                        ]);
                    }

                    $createdTasks[] = $task;
                }
            }

            return response()->json([
                'message' => 'Tasks created successfully',
                'tasks'   => $createdTasks,
                'count'   => count($createdTasks),
            ], 201);
        }

        // ── Manual mode (flat teacher_ids) ─────────────────────────
        $request->validate([
            'title'          => 'required|string|max:255',
            'duty_id'        => 'nullable|exists:duties,id',
            'teacher_ids'    => 'required|array',
            'teacher_ids.*'  => 'exists:users,id',
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable|string',
            'instructions'   => 'nullable|string',
        ]);

        $teacherIds   = $request->teacher_ids;
        $createdTasks = [];

        $teachers = \App\Models\User::whereIn('id', $teacherIds)->get()->keyBy('id');

        foreach ($teacherIds as $teacherId) {
            $task = Task::create([
                'title'          => $request->title,
                'duty_id'        => $request->duty_id,
                'assigned_to'    => $teacherId,
                'scheduled_date' => $request->scheduled_date,
                'scheduled_time' => $request->scheduled_time,
                'instructions'   => $request->instructions,
                'status'         => 'pending',
            ]);

            $assignedUser = $teachers->get($teacherId);
            if ($assignedUser) {
                $assignedUser->notify(new \App\Notifications\TaskAssigned($task, Auth::user()));

                \App\Jobs\SendPushNotificationJob::dispatch($teacherId, [
                    'title' => 'New Task Assigned',
                    'body'  => Auth::user()->name . ' assigned you a task: ' . $task->title,
                    'url'   => '/tasks/' . $task->id,
                    'tag'   => 'task-assigned-' . $task->id,
                ]);
            }

            $createdTasks[] = $task;
        }

        return response()->json([
            'message' => 'Tasks created successfully',
            'tasks'   => $createdTasks,
            'count'   => count($createdTasks),
        ], 201);
    }

    /**
     * Single Task Details
     */
    public function show(Task $task)
    {
        $task->load('duty', 'assignedTo');

        return $task;
    }

    /**
     * Mark task as completed
     */
    public function markComplete(Task $task)
    {
        $task->update([
            'status' => 'completed',
        ]);

        return response()->json(['message' => 'Task completed!']);
    }

    /**
     * Bulk Delete tasks — Principal Only
     */
    public function bulkDelete(Request $request)
    {
        if (Auth::user()->role !== 'principal' && Auth::user()->role !== 'manager') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
        ]);

        Task::whereIn('id', $request->task_ids)->delete();

        return response()->json(['message' => 'Tasks deleted successfully']);
    }

   
}
