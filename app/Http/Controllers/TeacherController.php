<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('scope') === 'assignable') {
            return User::whereIn('role', ['principal', 'manager', 'teacher'])
                ->select('id', 'name', 'role', 'can_review_achievements')
                ->orderBy('name')
                ->get();
        }
        return User::whereIn('role', ['teacher', 'principal'])
            ->withCount(['duties', 'tasks'])->get();

    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'role' => 'required',
            'phone' => 'nullable',
            'department' => 'nullable|string',
            'can_review_achievements' => 'boolean',
        ]);

        $validated['password'] = bcrypt('password123'); // default password
        // Ensure default is false if not provided
        $validated['can_review_achievements'] = $validated['can_review_achievements'] ?? false;

        $teacher = User::create($validated);

        return response()->json($teacher);
    }

    public function show($id)
    {
        $teacher = User::where('id', $id)
            ->whereIn('role', ['teacher', 'principal'])
            ->with([
                'duties',
                'tasks' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->firstOrFail();

        return response()->json($teacher);
    }

    public function deactivate(User $teacher)
    {
        $current = auth()->user();
        

        // Safety checks
        if (! in_array($teacher->role, ['teacher'])) {
            return response()->json(['message' => 'Only teachers can be deactivated'], 422);
        }

        if ($teacher->id === $current->id) {
            return response()->json(['message' => 'You cannot deactivate yourself'], 422);
        }

        DB::transaction(function () use ($teacher) {
            // 1) Detach from duties (pivot: duty_teacher)
            $teacher->duties()->detach();

            // 2) Handle pending tasks
            Task::where('assigned_to', $teacher->id)
                ->where('status', 'pending')
                 ->update(['status' => 'missed']); // or keep 'pending' and set assigned_to = null);

            // 3) Soft delete teacher
            $teacher->delete();
        });

        return response()->json([
            'message' => 'Teacher deactivated successfully',
        ]);
    }

    public function toggleReviewPermission(User $teacher)
    {
        // Only allow principal (checked via policy or middleware ideally, but here simple check)
        if (auth()->user()->role !== 'principal') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $teacher->update([
            'can_review_achievements' => !$teacher->can_review_achievements
        ]);

        return response()->json([
            'message' => 'Permission updated',
            'can_review_achievements' => $teacher->can_review_achievements
        ]);
    }
}
