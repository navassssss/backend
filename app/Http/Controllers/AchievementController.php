<?php

namespace App\Http\Controllers;

use App\Events\AchievementApproved;
use App\Models\Achievement;
use App\Models\AchievementCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $categories = AchievementCategory::where('is_active', true)->get();

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
            'attachments' => 'nullable|array',
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
                $path = $file->store('achievements', 'public');

                $achievement->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
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
        $user = $request->user();
        if ($user->role !== 'principal' && !$user->can_review_achievements) {
            return response()->json(['message' => 'Unauthorized'], 403);
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
        $user = $request->user();
        if ($user->role !== 'principal' && !$user->can_review_achievements) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'review_note' => 'required|string',
        ]);

        $achievement->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'review_note' => $validated['review_note'],
        ]);

        return response()->json($achievement);
    }
}
