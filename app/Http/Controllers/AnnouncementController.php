<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    public function __construct(private readonly WebPushService $push) {}

    // ─────────────────────────────────────────────────────────────────
    //  STAFF (Principal / Permitted Teacher) endpoints
    // ─────────────────────────────────────────────────────────────────

    /**
     * List announcements for staff management view.
     * Principals see all; permitted teachers see their own.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $canManage = $this->canManage($user);

        $query = Announcement::with(['creator:id,name', 'attachments'])
            ->withCount('reads');

        if (!$canManage) {
            // Teacher can only see own announcements and those targeted at them
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere(fn ($q2) => $q2->visibleToTeacher($user));
            });
        }

        // Filter by audience type
        if ($request->filled('audience_type')) {
            $query->where('audience_type', $request->audience_type);
        }

        return $query->orderByDesc('is_pinned')
                     ->orderByDesc('created_at')
                     ->get()
                     ->map(fn (Announcement $a) => $this->formatAnnouncement($a, $user));
    }

    /**
     * Create a new announcement.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$this->canManage($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'audience_type'=> 'required|in:teachers,students',
            'target_type'  => 'required|in:all,class,specific',
            'target_ids'   => 'nullable|array',           // user IDs or class IDs
            'target_ids.*' => 'integer',
            'is_pinned'    => 'boolean',
            'publish_now'  => 'boolean',
            'attachments'  => 'nullable|array',
            'attachments.*'=> 'file|max:10240',
        ]);

        $announcement = Announcement::create([
            'created_by'    => $user->id,
            'title'         => $validated['title'],
            'content'       => $validated['content'],
            'audience_type' => $validated['audience_type'],
            'target_type'   => $validated['target_type'],
            'is_pinned'     => $validated['is_pinned'] ?? false,
            'published_at'  => ($validated['publish_now'] ?? true) ? now() : null,
        ]);

        // Attach targets
        $this->syncTargets($announcement, $validated);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('announcement_attachments', 'public');
                $announcement->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        // ── Real-time Push Notification ──
        if ($announcement->published_at) {
            $payload = [
                'title'           => '📣 ' . $announcement->title,
                'body'            => \Illuminate\Support\Str::limit(strip_tags($announcement->content), 100),
                'url'             => $announcement->audience_type === 'students' ? '/student/announcements' : '/announcements',
                'tag'             => "announcement-" . $announcement->id,
                'announcement_id' => $announcement->id
            ];

            if ($announcement->target_type === 'all') {
                if ($announcement->audience_type === 'teachers') {
                    // Send to all staff via Queue
                    $teacherIds = User::where('role', '!=', 'student')->pluck('id')->toArray();
                    \App\Jobs\SendBulkPushNotification::dispatch($teacherIds, $payload);
                } else {
                    // Send to everyone (broadcast)
                    $allIds = User::pluck('id')->toArray();
                    \App\Jobs\SendBulkPushNotification::dispatch($allIds, $payload);
                }
            } elseif ($announcement->target_type === 'specific') {
                \App\Jobs\SendBulkPushNotification::dispatch($validated['target_ids'] ?? [], $payload);
            } elseif ($announcement->target_type === 'class') {
                // Find all students in these classes via Queue
                $studentIds = User::whereIn('class_id', $validated['target_ids'] ?? [])->pluck('id')->toArray();
                \App\Jobs\SendBulkPushNotification::dispatch($studentIds, $payload);
            }
        }

        return response()->json([
            'message'      => 'Announcement created successfully',
            'announcement' => $announcement->load(['creator:id,name', 'attachments', 'targetUsers:id,name', 'targetClasses:id,name']),
        ], 201);
    }

    /**
     * Show single announcement (staff).
     */
    public function show(Announcement $announcement)
    {
        $user = Auth::user();

        $announcement->loadCount('reads');
        $announcement->load([
            'creator:id,name',
            'attachments',
            'targetUsers:id,name,email',
            'targetClasses:id,name',
        ]);

        return response()->json($this->formatAnnouncement($announcement, $user, true));
    }

    /**
     * Update an announcement.
     */
    public function update(Request $request, Announcement $announcement)
    {
        $user = Auth::user();
        if (!$this->canManage($user) && $announcement->created_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'content'      => 'sometimes|string',
            'audience_type'=> 'sometimes|in:teachers,students',
            'target_type'  => 'sometimes|in:all,class,specific',
            'target_ids'   => 'nullable|array',
            'target_ids.*' => 'integer',
            'is_pinned'    => 'boolean',
            'publish_now'  => 'boolean',
        ]);

        $announcement->update(array_filter([
            'title'         => $validated['title'] ?? null,
            'content'       => $validated['content'] ?? null,
            'audience_type' => $validated['audience_type'] ?? null,
            'target_type'   => $validated['target_type'] ?? null,
            'is_pinned'     => $validated['is_pinned'] ?? null,
            'published_at'  => isset($validated['publish_now']) && $validated['publish_now']
                                    ? now()
                                    : $announcement->published_at,
        ], fn ($v) => !is_null($v)));

        $this->syncTargets($announcement, $validated);

        return response()->json([
            'message'      => 'Announcement updated',
            'announcement' => $announcement->fresh(['creator:id,name', 'attachments', 'targetUsers:id,name', 'targetClasses:id,name']),
        ]);
    }

    /**
     * Delete an announcement.
     */
    public function destroy(Announcement $announcement)
    {
        $user = Auth::user();
        if (!$this->canManage($user) && $announcement->created_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Remove attachment files from storage
        foreach ($announcement->attachments as $att) {
            Storage::disk('public')->delete($att->file_path);
        }

        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted']);
    }

    /**
     * Mark as pinned / unpin.
     */
    public function togglePin(Announcement $announcement)
    {
        $user = Auth::user();
        if (!$this->canManage($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $announcement->update(['is_pinned' => !$announcement->is_pinned]);

        return response()->json(['is_pinned' => $announcement->is_pinned]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  STUDENT-FACING endpoint
    // ─────────────────────────────────────────────────────────────────

    /**
     * Fetch announcements for the logged-in student.
     */
    public function studentIndex(Request $request)
    {
        $user = Auth::user();

        $announcements = Announcement::published()
            ->visibleToStudent($user)
            ->with(['creator:id,name', 'attachments'])
            ->withCount('reads')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->get()
            ->map(function (Announcement $a) use ($user) {
                $data = $this->formatAnnouncement($a, $user);
                $data['is_read'] = AnnouncementRead::where('announcement_id', $a->id)
                    ->where('user_id', $user->id)->exists();
                return $data;
            });

        return response()->json($announcements);
    }

    /**
     * Mark an announcement as read (student).
     */
    public function markRead(Announcement $announcement)
    {
        $user = Auth::user();

        AnnouncementRead::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()]
        );

        return response()->json(['message' => 'Marked as read']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Teacher-facing feed (teacher sees announcements targeted at them)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Fetch announcements for the logged-in teacher (their feed).
     */
    public function teacherFeed(Request $request)
    {
        $user = Auth::user();

        $announcements = Announcement::published()
            ->visibleToTeacher($user)
            ->with(['creator:id,name', 'attachments'])
            ->withCount('reads')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->get()
            ->map(function (Announcement $a) use ($user) {
                $data = $this->formatAnnouncement($a, $user);
                $data['is_read'] = AnnouncementRead::where('announcement_id', $a->id)
                    ->where('user_id', $user->id)->exists();
                return $data;
            });

        return response()->json($announcements);
    }

    /**
     * Mark a teacher announcement as read.
     */
    public function teacherMarkRead(Announcement $announcement)
    {
        $user = Auth::user();

        AnnouncementRead::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()]
        );

        return response()->json(['message' => 'Marked as read']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────

    private function canManage(User $user): bool
    {
        return $user->role === 'principal'
            || $user->is_vice_principal
            || $user->hasPermission('manage_announcements');
    }

    private function syncTargets(Announcement $announcement, array $validated): void
    {
        $targetType = $validated['target_type'] ?? $announcement->target_type;
        $targetIds  = $validated['target_ids'] ?? [];

        if ($targetType === 'specific') {
            $announcement->targetUsers()->sync($targetIds);
            $announcement->targetClasses()->sync([]);
        } elseif ($targetType === 'class') {
            $announcement->targetClasses()->sync($targetIds);
            $announcement->targetUsers()->sync([]);
        } else {
            // 'all' — clear specific targets
            $announcement->targetUsers()->sync([]);
            $announcement->targetClasses()->sync([]);
        }
    }

    private function formatAnnouncement(Announcement $a, User $user, bool $detail = false): array
    {
        $base = [
            'id'            => $a->id,
            'title'         => $a->title,
            'content'       => $a->content,
            'audience_type' => $a->audience_type,
            'target_type'   => $a->target_type,
            'is_pinned'     => $a->is_pinned,
            'published_at'  => $a->published_at,
            'created_at'    => $a->created_at,
            'creator'       => $a->creator,
            'attachments'   => $a->attachments,
            'reads_count'   => (int) ($a->reads_count ?? 0),
        ];

        if ($detail) {
            $base['target_users']   = $a->targetUsers;
            $base['target_classes'] = $a->targetClasses;
        }

        return $base;
    }
}
