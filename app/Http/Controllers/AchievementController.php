<?php

namespace App\Http\Controllers;

use App\Events\AchievementApproved;
use App\Events\AchievementRevoked;
use App\Models\Achievement;
use App\Models\AchievementCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            ->get();

        return response()->json($achievements);
    }

    /**
     * Get all achievements (for principal to review)
     */
    public function all(Request $request)
    {
        $query = Achievement::with(['student.user', 'student.class', 'category', 'attachments']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $achievements = $query->latest()->get();

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
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|max:10240', // 10MB max per file
        ]);

        // Get category and snapshot points
        $category = AchievementCategory::findOrFail($validated['achievement_category_id']);

        $achievement = Achievement::create([
            'student_id' => $student->id,
            'achievement_category_id' => $category->id,
            'title' => $validated['title'] ?? $category->name,
            'description' => $validated['description'] ?? null,
            'points' => $category->points, // Snapshot
            'status' => 'pending',
        ]);

        // Handle file uploads
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $cleanName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $extension = $file->getClientOriginalExtension();
                $safeFileName = Str::limit($cleanName, 40, '') . '-' . uniqid() . '.' . $extension;

                $path = $file->storeAs('achievements', $safeFileName, 'public');

                $achievement->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(), // preserve original for display
                    'mime_type' => $file->getMimeType(),
                ]);
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

        if ($achievement->status === 'approved') {
            return response()->json(['message' => 'Achievement is already approved.'], 422);
        }

        $request->validate([
            'review_note' => 'nullable|string',
        ]);

        $achievement->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'review_note' => $request->review_note,
        ]);

        // Fire event to update points
        event(new AchievementApproved($achievement->fresh(['student.class', 'category'])));

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
