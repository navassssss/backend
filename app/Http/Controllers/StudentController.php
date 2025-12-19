<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Get student by username (public profile)
     */
    public function show(string $username)
    {
        $student = Student::where('username', $username)
            ->with(['user', 'class', 'achievements' => function ($query) {
                $query->where('status', 'approved')
                    ->with('category')
                    ->latest()
                    ->limit(10);
            }])
            ->firstOrFail();

        return response()->json($student);
    }

    /**
     * Get current student's profile
     */
    public function profile(Request $request)
    {
        $student = $request->user()->student;
        $student->load(['class', 'achievements.category']);

        return response()->json($student);
    }

    /**
     * Update current student's profile
     */
    public function update(Request $request)
    {
        $student = $request->user()->student;

        $validated = $request->validate([
            'photo' => 'nullable|string',
        ]);

        $student->update($validated);

        return response()->json($student);
    }
}
