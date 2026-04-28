<?php

namespace App\Http\Controllers;

use App\Models\IssueCategory;
use Illuminate\Http\Request;

class IssueCategoryController extends Controller
{
    public function index()
    {
        return IssueCategory::orderBy('name')->get();
    }

    /**
     * Create a new category
     */
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string|unique:issue_categories,name|max:255'
    //     ]);

    //     $category = IssueCategory::create([
    //         'name' => $request->name
    //     ]);

    //     return response()->json([
    //         'message' => 'Category created successfully',
    //         'category' => $category
    //     ], 201);
    // }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);

        return IssueCategory::create([
            'name' => $request->name,
        ]);
    }

    /**
     * Update category name
     */
    public function update(Request $request, IssueCategory $category)
    {
        $request->validate([
            'name' => 'required|string|unique:issue_categories,name,'.$category->id,
        ]);

        $category->update(['name' => $request->name]);

        return response()->json([
            'message' => 'Category updated',
            'category' => $category,
        ]);
    }

    /**
     * Delete category
     */
    public function destroy(IssueCategory $category)
    {
        $category->delete();

        return response()->json([
            'message' => 'Category removed',
        ]);
    }
}
