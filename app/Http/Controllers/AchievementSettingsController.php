<?php

namespace App\Http\Controllers;

use App\Models\AchievementCategory;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AchievementSettingsController extends Controller
{
    /**
     * Get categories and settings.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('review_achievements')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $categories = AchievementCategory::orderBy('name')->get();
        $starThresholds = json_decode(Setting::getValue('star_thresholds', '{}'), true);

        return response()->json([
            'categories' => $categories,
            'star_thresholds' => $starThresholds,
        ]);
    }

    /**
     * Store new category.
     */
    public function storeCategory(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('review_achievements')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $category = AchievementCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'points' => $validated['points'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Update category.
     */
    public function updateCategory(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermission('review_achievements')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category = AchievementCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $category->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'points' => $validated['points'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($category);
    }

    /**
     * Delete category.
     */
    public function destroyCategory(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermission('review_achievements')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category = AchievementCategory::findOrFail($id);
        
        // Cannot delete if there are ongoing achievements, maybe just deactivate it or delete if unused.
        if ($category->achievements()->count() > 0) {
            $category->update(['is_active' => false]);
            return response()->json(['message' => 'Category has achievements and was deactivated instead.'], 200);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted'], 200);
    }

    /**
     * Update star thresholds.
     */
    public function updateThresholds(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('review_achievements')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'thresholds' => 'required|array', // e.g. ["1" => 20, "2" => 50]
        ]);

        Setting::setValue('star_thresholds', json_encode($validated['thresholds']));
        Cache::forget('star_thresholds');

        return response()->json(['message' => 'Thresholds updated successfully']);
    }
}
