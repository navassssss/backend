<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IssueController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Issue::with([
            'category:id,name',
            'creator:id,name',
            'responsibleUser:id,name',
        ]);

        // Principal / manager: see all
        if (! in_array($user->role, ['principal', 'manager'])) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('responsible_user_id', $user->id)
                    ->orWhere('visibility', 'public');
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'open') {
                 $query->whereIn('status', ['open', 'forwarded']);
            } else {
                 $query->where('status', $request->status);
            }
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'title' => 'required|string|min:3',
            'description' => 'required|string',
            'category_id' => 'nullable|exists:issue_categories,id',
            'priority' => 'required|in:low,medium,high',
            'visibility' => 'nullable|in:public,restricted',
            'duty_id' => 'nullable|exists:duties,id',
            'related_teacher_id' => 'nullable|exists:users,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'task_id' => 'nullable|exists:tasks,id',
        ]);

        $issue = Issue::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'category_id' => $data['category_id'] ?? null,
            'priority' => $data['priority'],
            'visibility' => $data['visibility'] ?? 'public',
            'duty_id' => $data['duty_id'] ?? null,
            'related_teacher_id' => $data['related_teacher_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'created_by' => $user->id,
            'responsible_user_id' => $data['responsible_user_id'] ?? null, // principal will forward
            'status' => 'open',
        ]);

        $issue->actions()->create([
            'performed_by' => $user->id,
            'action_type' => 'created',
            'note' => null,
        ]);

        return response()->json([
            'message' => 'Issue created successfully',
            'issue' => $issue->load('category', 'creator', 'responsibleUser'),
        ], 201);
    }

    public function show(Issue $issue)
    {
        $user = Auth::user();

        // visibility check
        if (! in_array($user->role, ['principal', 'manager'])) {
            if ($issue->visibility === 'restricted' &&
                $issue->created_by !== $user->id &&
                $issue->responsible_user_id !== $user->id
            ) {
                abort(403, 'You are not allowed to view this issue');
            }
        }

        $issue->load([
            'category:id,name',
            'creator:id,name',
            'responsibleUser:id,name',
            'relatedTeacher:id,name',
            'duty:id,name',
            'task:id,title',
        ]);

        $comments = $issue->comments()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();

        $timeline = $issue->actions()
            ->with(['performer:id,name', 'fromUser:id,name', 'toUser:id,name'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'issue' => $issue,
            'comments' => $comments,
            'timeline' => $timeline,
        ]);
    }

    public function addComment(Request $request, Issue $issue)
    {
        $data = $request->validate([
            'comment' => 'required|string|min:2',
        ]);

        $user = Auth::user();

        // Authorization: Principal/Manager, Creator, or Responsible User can comment
        $canComment = in_array($user->role, ['principal', 'manager']) ||
                      $issue->created_by === $user->id ||
                      $issue->responsible_user_id === $user->id;

        if (!$canComment) {
            abort(403, 'You are not authorized to comment on this issue.');
        }

        // Authorization: Principal/Manager, Creator, or Responsible User can comment
        $canComment = in_array($user->role, ['principal', 'manager']) ||
                      $issue->created_by === $user->id ||
                      $issue->responsible_user_id === $user->id;

        if (!$canComment) {
            abort(403, 'You are not authorized to comment on this issue.');
        }

        $comment = $issue->comments()->create([
            'user_id' => $user->id,
            'comment' => $data['comment'],
        ]);

        $issue->actions()->create([
            'performed_by' => $user->id,
            'action_type' => 'commented',
            'note' => $data['comment'],
        ]);

        return $comment->load('user:id,name');
    }

    public function forward(Request $request, Issue $issue)
    {
        $data = $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'note' => 'nullable|string',
        ]);

        $user = Auth::user();

        // principal & manager can forward to anyone; normal teacher can forward too if they are creator or current responsible
        if (! in_array($user->role, ['principal', 'manager'])) {
            if ($issue->created_by !== $user->id && $issue->responsible_user_id !== $user->id) {
                abort(403, 'You are not allowed to forward this issue');
            }
        }

        $fromUserId = $issue->responsible_user_id;

        $issue->update([
            'responsible_user_id' => $data['to_user_id'],
            'status' => 'forwarded',
        ]);

        // Notify the new teacher
        $newTeacher = User::find($data['to_user_id']);
        if ($newTeacher) {
            $newTeacher->notify(new \App\Notifications\IssueForwarded($issue, $user));
        }

        $issue->actions()->create([
            'performed_by' => $user->id,
            'action_type' => 'forwarded',
            'from_user_id' => $fromUserId,
            'to_user_id' => $data['to_user_id'],
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Issue forwarded',
            'issue' => $issue->load('responsibleUser:id,name'),
        ]);
    }

    public function resolve(Issue $issue)
    {
        $user = Auth::user();

        if (! in_array($user->role, ['principal', 'manager'])) {
            if ($issue->responsible_user_id !== $user->id) {
                abort(403, 'Only responsible user or principal/manager can resolve');
            }
        }

        $issue->update([
            'status' => 'resolved',
        ]);

        $issue->actions()->create([
            'performed_by' => $user->id,
            'action_type' => 'resolved',
            'note' => null,
        ]);

        // Notify Creator and Responsible User (if different from resolver)
        $usersToNotify = collect([$issue->created_by, $issue->responsible_user_id])
            ->unique()
            ->reject(fn($id) => $id === $user->id);

        foreach ($usersToNotify as $uid) {
            $u = User::find($uid);
            if ($u) {
                $u->notify(new \App\Notifications\IssueResolved($issue, $user));
            }
        }

        return response()->json([
            'message' => 'Issue resolved',
            'issue' => $issue,
        ]);
    }
}
