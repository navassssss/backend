<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Task::with('duty', 'assignedTo');

        // If teacher_id is provided → filter by teacher
        if ($request->has('teacher_id')) {
            return $query->where('assigned_to', $request->teacher_id)->get();
        }

        // If principal → return all tasks
        if ($user->role === 'principal') {
            return $query->get();
        }

        // If teacher → only own tasks
        return $query->where('assigned_to', $user->id)->get();
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

        $teacherIds = $request->teacher_ids;
        $createdTasks = [];

        // Create a task for each teacher
        foreach ($teacherIds as $teacherId) {
            $task = Task::create([
                'title' => $request->title,
                'duty_id' => $request->duty_id,
                'assigned_to' => $teacherId,
                'scheduled_date' => $request->scheduled_date,
                'scheduled_time' => $request->scheduled_time,
                'instructions' => $request->instructions,
                'status' => 'pending',
            ]);

            // Send notification
            $assignedUser = \App\Models\User::find($teacherId);
            if ($assignedUser) {
                $assignedUser->notify(new \App\Notifications\TaskAssigned($task, Auth::user()));
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

   
}
