<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Subject::with(['classRoom', 'teacher']);

        // Role-based filtering
        if ($user->role === 'teacher') {
            // Teachers see only subjects they teach
            $query->where('teacher_id', $user->id);
        }
        // Principals/managers see all subjects (no filter)

        $subjects = $query->get()
            ->map(function($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'className' => $subject->classRoom->name,
                    'classId' => $subject->class_id,
                    'teacherName' => $subject->teacher->name,
                    'teacherId' => $subject->teacher_id,
                    'finalMaxMarks' => $subject->final_max_marks,
                    'isLocked' => $subject->is_locked
                ];
            });

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'class_id' => 'required|exists:class_rooms,id',
            'teacher_id' => 'required|exists:users,id',
            'final_max_marks' => 'required|integer|min:1|max:100'
        ]);

        $subject = Subject::create($validated);

        return response()->json([
            'message' => 'Subject created successfully',
            'subject' => $subject
        ], 201);
    }

    public function show($id)
    {
        $subject = Subject::with(['classRoom', 'teacher', 'works'])->findOrFail($id);

        return response()->json([
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'className' => $subject->classRoom->name,
            'classId' => $subject->class_id,
            'teacherName' => $subject->teacher->name,
            'teacherId' => $subject->teacher_id,
            'finalMaxMarks' => $subject->final_max_marks,
            'isLocked' => $subject->is_locked,
            'worksCount' => $subject->works->count()
        ]);
    }

    public function update(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50',
            'teacher_id' => 'sometimes|exists:users,id',
            'final_max_marks' => 'sometimes|integer|min:1|max:100'
        ]);

        $subject->update($validated);

        return response()->json([
            'message' => 'Subject updated successfully',
            'subject' => $subject
        ]);
    }

    public function toggleLock($id)
    {
        $subject = Subject::findOrFail($id);
        $subject->is_locked = !$subject->is_locked;
        $subject->save();

        return response()->json([
            'message' => 'Subject lock status updated',
            'isLocked' => $subject->is_locked
        ]);
    }
}
