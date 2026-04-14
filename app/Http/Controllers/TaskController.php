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
     * Create a scheduled task — Principal Only
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'duty_id' => 'nullable|exists:duties,id',
            'teacher_ids' => 'required|array',
            'teacher_ids.*' => 'exists:users,id',
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable',
            'instructions' => 'nullable|string',
        ]);

        $teacherIds   = $request->teacher_ids;
        $createdTasks = [];

        // Batch-load all assigned teachers in one query
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
            'tasks' => $createdTasks,
            'count' => count($createdTasks)
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
