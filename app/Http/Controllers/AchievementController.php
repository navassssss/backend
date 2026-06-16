<?php

namespace App\Http\Controllers;

use App\Events\AchievementApproved;
use App\Events\AchievementRevoked;
use App\Models\Achievement;
use App\Models\AchievementCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AchievementController extends Controller
{
    /**
     * Get student's own achievements
     */
    public function index(Request $request)
    {
        $student = $request->user()->student;

        $achievements = Achievement::where('student_id', $student->id)
            ->with(['category', 'approver', 'attachments'])
            ->latest()
            ->get()
            ->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'title' => $achievement->title,
                    'description' => $achievement->description,
                    'points' => $achievement->points,
                    'status' => $achievement->status,
                    'review_note' => $achievement->review_note,
                    'created_at' => $achievement->created_at,
                    'approved_at' => $achievement->approved_at,
                    'category' => $achievement->category ? $achievement->category->only(['id', 'name', 'points']) : null,
                    'approver' => $achievement->approver ? $achievement->approver->only(['id', 'name']) : null,
                    'attachments' => $achievement->attachments->map->only(['id', 'file_path', 'file_name', 'mime_type']),
                ];
            });

        return response()->json($achievements);
    }

    /**
     * Get a lightweight summary of achievements for the student dashboard
     */
    public function summary(Request $request)
    {
        $student = $request->user()->student;

        $pendingCount = Achievement::where('student_id', $student->id)
            ->where('status', 'pending')
            ->count();

        $recent = Achievement::where('student_id', $student->id)
            ->where('status', 'approved')
            ->with(['category'])
            ->latest('approved_at')
            ->take(4)
            ->get()
            ->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'title' => $achievement->title,
                    'points' => $achievement->points,
                    'status' => $achievement->status,
                    'created_at' => $achievement->created_at,
                    'category' => $achievement->category ? ['name' => $achievement->category->name] : null,
                ];
            });

        return response()->json([
            'recent_achievements' => $recent,
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Get all achievements (for principal to review)
     */
    public function all(Request $request)
    {
        $query = Achievement::with(['student.user', 'student.class', 'category', 'attachments']);

        // Filter by student_id if provided
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        // Filter by status if provided
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by title or student name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('student.user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Sort order
        $sort = $request->query('sort', 'date_desc');
        switch ($sort) {
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'points_desc':
                $query->orderBy('points', 'desc');
                break;
            case 'points_asc':
                $query->orderBy('points', 'asc');
                break;
            case 'date_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Check if pagination is explicitly requested or page parameter is present
        if ($request->has('page') || $request->has('wants_pagination')) {
            $perPage = $request->query('per_page', 15);
            $achievements = $query->paginate($perPage);
            $pendingCount = Achievement::where('status', 'pending')->count();

            return response()->json([
                'achievements' => $achievements,
                'pending_count' => $pendingCount,
            ]);
        }

        // Backwards compatibility: return the full unpaginated list
        $achievements = $query->get();
        return response()->json($achievements);
    }

    /**
     * Get achievement categories
     */
    public function categories()
    {
        $categories = Cache::remember('achievement:categories', now()->addHours(24), function () {
            return AchievementCategory::where('is_active', true)->get();
        });

        return response()->json($categories);
    }

    /**
     * Submit a new achievement
     */
    public function store(Request $request)
    {
        $student = $request->user()->student;

        $validated = $request->validate([
            'achievement_category_id' => 'required|exists:achievement_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|mimes:jpeg,png,jpg,pdf|max:10240', // 10MB max per file
        ]);

        // Get category and snapshot points
        $category = AchievementCategory::findOrFail($validated['achievement_category_id']);

        // Prevent double entries within a 5-minute window
        $isDuplicate = Achievement::where('student_id', $student->id)
            ->where('achievement_category_id', $category->id)
            ->where('title', $validated['title'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($isDuplicate) {
            return response()->json(['message' => 'You recently submitted this exact achievement. Please wait before submitting again.'], 429);
        }

        $achievement = Achievement::create([
            'student_id' => $student->id,
            'achievement_category_id' => $category->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'points' => $category->points, // Snapshot
            'status' => 'pending',
        ]);

        // Handle file uploads
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                // Manually read the file and put it into storage to bypass move_uploaded_file
                // which often fails on Windows XAMPP environments across different drives/permissions.
                $hashName = $file->hashName();
                $destination = 'achievements/' . $hashName;

                $success = \Illuminate\Support\Facades\Storage::disk('public')->put(
                    $destination,
                    file_get_contents($file->getRealPath())
                );

                if ($success) {
                    $achievement->attachments()->create([
                        'file_path' => $destination,
                        'file_name' => $file->getClientOriginalName(), // preserve original for display
                        'mime_type' => $file->getMimeType(),
                    ]);
                } else {
                    Log::error('File upload failed via Storage::put', [
                        'file' => $file->getClientOriginalName(),
                        'error' => $file->getError()
                    ]);
                }
            }
        }

        return response()->json($achievement->load('attachments'), 201);
    }

    /**
     * Approve achievement (Principal only)
     */
    public function approve(Request $request, Achievement $achievement)
    {
        \Illuminate\Support\Facades\Gate::authorize('review', Achievement::class);

        $request->validate([
            'review_note' => 'nullable|string',
        ]);

        // Perform an atomic conditional update: only approve if status still 'pending'.
        $updateData = [
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'review_note' => $request->review_note,
        ];

        $rows = Achievement::where('id', $achievement->id)
            ->where('status', 'pending')
            ->update($updateData);

        if ($rows === 0) {
            return response()->json(['message' => 'Achievement could not be approved. It may have been processed already.'], 422);
        }

        // Reload the fresh achievement with relations for the event payload
        $achievement = Achievement::with(['student.class', 'category'])->find($achievement->id);

        // Fire event to update points (listener will run once)
        event(new AchievementApproved($achievement));

        return response()->json($achievement);
    }

    /**
     * Reject achievement (Principal only)
     */
    public function reject(Request $request, Achievement $achievement)
    {
        \Illuminate\Support\Facades\Gate::authorize('review', Achievement::class);

        if ($achievement->status === 'rejected') {
            return response()->json(['message' => 'Achievement is already rejected.'], 422);
        }

        $wasApproved = $achievement->status === 'approved';

        $validated = $request->validate([
            'review_note' => 'required|string',
        ]);

        $achievement->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'review_note' => $validated['review_note'],
        ]);

        if ($wasApproved) {
            event(new AchievementRevoked($achievement->fresh(['student.class', 'category'])));
        }

        return response()->json($achievement);
    }
}
