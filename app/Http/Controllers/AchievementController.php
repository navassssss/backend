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
