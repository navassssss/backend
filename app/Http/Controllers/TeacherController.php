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
            ->withCount(['duties', 'tasks'])
            ->with('permissions')
            ->get();

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

    public function update(Request $request, $id)
    {
        $teacher = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable',
            'department' => 'nullable|string',
        ]);

        $teacher->update($validated);

        return response()->json($teacher);
    }

    public function show($id)
    {
        $teacher = User::where('id', $id)
            ->whereIn('role', ['teacher', 'principal'])
            ->with([
                'duties',
                'permissions',
                'tasks' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->firstOrFail();

        $classes = \App\Models\ClassRoom::where('class_teacher_id', $id)->get();
        $reports = \App\Models\Report::where('teacher_id', $id)->with('duty', 'task')->latest()->take(10)->get();
        // Subjects & CCE Works
        $subjects = \App\Models\Subject::where('teacher_id', $id)
            ->with('classRoom')
            ->withCount('works as total_works_assigned')
            ->with(['works' => function ($q) {
                $q->withCount([
                    'submissions as total_submissions',
                    'submissions as evaluated_submissions' => function ($sq) {
                        $sq->whereNotNull('evaluated_at')->orWhere('status', 'evaluated');
                    }
                ]);
            }])
            ->get()
            ->map(function ($subject) {
                $totalSubmissions = $subject->works->sum('total_submissions');
                $evaluatedSubmissions = $subject->works->sum('evaluated_submissions');
                $subject->completion_percent = $totalSubmissions > 0 ? (int)round(($evaluatedSubmissions / $totalSubmissions) * 100) : 0;
                unset($subject->works); // Keep payload small
                return $subject;
            });

        $teacher->assigned_classes = $classes;
        $teacher->recent_reports = $reports;
        $teacher->subjects = $subjects;

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

    public function toggleVicePrincipal(User $teacher)
    {
        $actor = auth()->user();

        // Only a real principal (by role) can grant/revoke vice-principal status
        if (!in_array($actor->role, ['principal', 'manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only applicable to teachers, not other principals/managers
        if ($teacher->role !== 'teacher') {
            return response()->json(['message' => 'Only teachers can be made vice-principal'], 422);
        }

        $teacher->update([
            'is_vice_principal' => !$teacher->is_vice_principal
        ]);

        return response()->json([
            'message' => $teacher->is_vice_principal
                ? "{$teacher->name} is now a Vice-Principal"
                : "{$teacher->name}'s Vice-Principal access has been revoked",
            'is_vice_principal' => $teacher->is_vice_principal
        ]);
    }

    public function syncPermissions(Request $request, User $teacher)
    {
        if (auth()->user()->role !== 'principal' && auth()->user()->role !== 'manager') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $permissionIds = \App\Models\Permission::whereIn('name', $validated['permissions'])->pluck('id');
        $teacher->permissions()->sync($permissionIds);

        return response()->json([
            'message' => 'Permissions updated successfully',
            'permissions' => $teacher->permissions()->get()
        ]);
    }
}
