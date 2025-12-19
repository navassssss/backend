<?php

namespace App\Http\Controllers;

use App\Models\Duty;
use Illuminate\Http\Request;

class DutyController extends Controller
{
    public function index()
    {
        // Principal can see all; later you can filter by role
        return Duty::with('teachers:id,name,email,role,department')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:responsibility,rotational'],
            'frequency' => ['required', 'in:none,daily,weekly,monthly,custom'],
            'teacher_ids' => ['array'],
            'teacher_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $teacherIds = $validated['teacher_ids'] ?? [];
        unset($validated['teacher_ids']);

        $validated['created_by'] = $request->user()->id;

        $duty = Duty::create($validated);

        if (! empty($teacherIds)) {
            $pivotData = [];
            foreach ($teacherIds as $teacherId) {
                $pivotData[$teacherId] = [
                    'assigned_by' => $request->user()->id,
                    'start_date' => now()->toDateString(),
                ];
            }
            $duty->teachers()->attach($pivotData);

            // Notify teachers
            foreach ($teacherIds as $tid) {
                $teacher = \App\Models\User::find($tid);
                if ($teacher) {
                    $teacher->notify(new \App\Notifications\DutyAssigned($duty, $request->user()));
                }
            }
        }

        return $duty->load('teachers:id,name,email,role,department');
    }

    public function show(Duty $duty)
    {
        return $duty->load('teachers:id,name,email,role,department');
    }

    public function update(Request $request, Duty $duty)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:responsibility,rotational'],
            'frequency' => ['sometimes', 'in:none,daily,weekly,monthly,custom'],
            'custom_days' => ['nullable', 'array'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $duty->update($validated);

        return $duty->fresh()->load('teachers:id,name,email,role,department');
    }

    public function destroy(Duty $duty)
    {
        $duty->delete();

        return response()->noContent();
    }

    // assign or sync teachers to a duty
    public function assignTeachers(Request $request, Duty $duty)
    {
        $validated = $request->validate([
            'teacher_ids' => ['required', 'array'],
            'teacher_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $syncData = [];
        foreach ($validated['teacher_ids'] as $index => $teacherId) {
            $syncData[$teacherId] = [
                'assigned_by' => $request->user()->id,
                'start_date' => now()->toDateString(),
                'order_index' => $index,
            ];
        }

        $duty->teachers()->syncWithoutDetaching($syncData);

        // Notify teachers
        foreach ($validated['teacher_ids'] as $tid) {
            $teacher = \App\Models\User::find($tid);
            if ($teacher) {
                $teacher->notify(new \App\Notifications\DutyAssigned($duty, $request->user()));
            }
        }

        return $duty->load('teachers:id,name,email,role,department');

    }

    public function removeTeacher(Duty $duty, Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:users,id',
        ]);

        $duty->teachers()->detach($request->teacher_id);

        return response()->json(['message' => 'Teacher removed']);
    }
}
