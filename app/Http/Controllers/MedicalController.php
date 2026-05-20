<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MedicalController extends Controller
{


    /**
     * Active medical cases
     */
    public function active()
    {
        \Illuminate\Support\Facades\Gate::authorize('viewAny', \App\Models\MedicalRecord::class);

        $records = MedicalRecord::active()
            ->with([
                'student.user',
                'student.classRoom',
                'reporter:id,name',
            ])
            ->orderByDesc('reported_at')
            ->get()
            ->map(fn ($r) => $this->format($r));

        return response()->json($records);
    }

    /**
     * History (resolved cases) — paginated
     */
    public function history(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('viewAny', \App\Models\MedicalRecord::class);

        $query = MedicalRecord::resolved()
            ->with([
                'student.user',
                'student.classRoom',
                'reporter:id,name',
                'recoveredBy:id,name',
                'sentHomeBy:id,name',
            ]);

        $sort = $request->input('sort', 'reported_at');
        if ($sort === 'resolved_at') {
            $query->orderByRaw('COALESCE(recovered_at, sent_home_at) DESC');
        } else {
            $query->orderByDesc('reported_at');
        }

        if ($request->filled('status_filter') && in_array($request->status_filter, ['recovered', 'sent_home'])) {
            if ($request->status_filter === 'recovered') {
                $query->whereNotNull('recovered_at');
            } else {
                $query->whereNotNull('sent_home_at');
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('student.user', fn ($q) => $q->where('name', 'like', "%$s%"))
                  ->orWhere('illness_name', 'like', "%$s%");
        }

        if ($request->filled('date')) {
            $query->whereDate('reported_at', $request->date);
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 20))
                  ->through(fn ($r) => $this->format($r))
        );
    }

    /**
     * Create a new medical record
     */
    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('create', \App\Models\MedicalRecord::class);

        $validated = $request->validate([
            'student_id'     => 'required|exists:students,id',
            'illness_name'   => 'required|string|max:255',
            'reported_at'    => 'required|date|before_or_equal:now',
            'went_to_doctor' => 'boolean',
            'notes'          => 'nullable|string|max:2000',
        ]);

        if (\App\Models\MedicalRecord::where('student_id', $validated['student_id'])->active()->exists()) {
            return response()->json(['message' => 'Student is already in the medical bay'], 422);
        }

        $record = MedicalRecord::create([
            ...$validated,
            'reported_by'    => Auth::id(),
            'went_to_doctor' => $validated['went_to_doctor'] ?? false,
        ]);

        $record->load('student.user', 'student.classRoom', 'reporter:id,name');

        return response()->json($this->format($record), 201);
    }

    /**
     * Mark as recovered
     */
    public function recover(Request $request, MedicalRecord $medical)
    {
        \Illuminate\Support\Facades\Gate::authorize('update', $medical);

        if ($medical->status !== 'active') {
            return response()->json(['message' => 'Record is already resolved'], 422);
        }

        $request->validate([
            'recovered_at' => 'nullable|date|before_or_equal:now',
        ]);

        $recoveredAt = $request->recovered_at ? \Carbon\Carbon::parse($request->recovered_at) : now();

        if ($recoveredAt->lt($medical->reported_at)) {
            return response()->json(['message' => 'Recovery time cannot be before the reported time'], 422);
        }

        $medical->update([
            'recovered_at' => $recoveredAt,
            'recovered_by' => Auth::id(),
        ]);

        return response()->json($this->format($medical->fresh('student.user', 'reporter', 'recoveredBy')));
    }

    /**
     * Mark as sent home
     */
    public function sentHome(Request $request, MedicalRecord $medical)
    {
        \Illuminate\Support\Facades\Gate::authorize('update', $medical);

        if ($medical->status !== 'active') {
            return response()->json(['message' => 'Record is already resolved'], 422);
        }

        $request->validate([
            'sent_home_at' => 'nullable|date|before_or_equal:now',
        ]);

        $sentHomeAt = $request->sent_home_at ? \Carbon\Carbon::parse($request->sent_home_at) : now();

        if ($sentHomeAt->lt($medical->reported_at)) {
            return response()->json(['message' => 'Sent home time cannot be before the reported time'], 422);
        }

        $medical->update([
            'sent_home_at' => $sentHomeAt,
            'sent_home_by' => Auth::id(),
        ]);

        return response()->json($this->format($medical->fresh('student.user', 'reporter', 'sentHomeBy')));
    }

    /**
     * Toggle doctor visit status
     */
    public function toggleDoctor(MedicalRecord $medical)
    {
        \Illuminate\Support\Facades\Gate::authorize('update', $medical);

        if ($medical->status !== 'active') {
            return response()->json(['message' => 'Cannot modify a resolved record'], 422);
        }

        $medical->update([
            'went_to_doctor' => !$medical->went_to_doctor
        ]);

        return response()->json(['went_to_doctor' => $medical->went_to_doctor]);
    }

    /**
     * Single record
     */
    public function show(MedicalRecord $medical)
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $medical);
        $medical->load('student.user', 'student.classRoom', 'reporter', 'recoveredBy', 'sentHomeBy');
        return response()->json($this->format($medical));
    }

    /**
     * Delete a medical record
     */
    public function destroy(MedicalRecord $medical)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete', $medical);
        $medical->delete();
        return response()->json(['message' => 'Record deleted successfully']);
    }

    /* ── Private formatter ── */
    private function format(MedicalRecord $r): array
    {
        $student = $r->student;
        return [
            'id'             => $r->id,
            'illness_name'   => $r->illness_name,
            'reported_at'    => $r->reported_at?->toISOString(),
            'went_to_doctor' => $r->went_to_doctor,
            'notes'          => $r->notes,
            'status'         => $r->status,
            'recovered_at'   => $r->recovered_at?->toISOString(),
            'sent_home_at'   => $r->sent_home_at?->toISOString(),
            'reported_by'    => $r->reporter ? ['id' => $r->reporter->id, 'name' => $r->reporter->name] : null,
            'recovered_by'   => $r->recoveredBy ? ['id' => $r->recoveredBy->id, 'name' => $r->recoveredBy->name] : null,
            'sent_home_by'   => $r->sentHomeBy ? ['id' => $r->sentHomeBy->id, 'name' => $r->sentHomeBy->name] : null,
            'student' => $student ? [
                'id'         => $student->id,
                'name'       => $student->user?->name ?? 'Unknown',
                'roll_number'=> $student->roll_number,
                'class'      => $student->classRoom?->name,
            ] : null,
        ];
    }
}
