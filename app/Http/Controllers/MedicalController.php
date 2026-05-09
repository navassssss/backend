<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MedicalController extends Controller
{
    private function authorize(): void
    {
        $user = Auth::user();
        if (!$user->isPrincipal() && !$user->hasPermission('manage_medical')) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Active medical cases
     */
    public function active()
    {
        $this->authorize();

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
        $this->authorize();

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
        $this->authorize();

        $validated = $request->validate([
            'student_id'     => 'required|exists:students,id',
            'illness_name'   => 'required|string|max:255',
            'reported_at'    => 'required|date',
            'went_to_doctor' => 'boolean',
            'notes'          => 'nullable|string|max:2000',
        ]);

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
        $this->authorize();

        if ($medical->status !== 'active') {
            return response()->json(['message' => 'Record is already resolved'], 422);
        }

        $request->validate([
            'recovered_at' => 'nullable|date',
        ]);

        $medical->update([
            'recovered_at' => $request->recovered_at ?? now(),
            'recovered_by' => Auth::id(),
        ]);

        return response()->json($this->format($medical->fresh('student.user', 'reporter', 'recoveredBy')));
    }

    /**
     * Mark as sent home
     */
    public function sentHome(Request $request, MedicalRecord $medical)
    {
        $this->authorize();

        if ($medical->status !== 'active') {
            return response()->json(['message' => 'Record is already resolved'], 422);
        }

        $request->validate([
            'sent_home_at' => 'nullable|date',
        ]);

        $medical->update([
            'sent_home_at' => $request->sent_home_at ?? now(),
            'sent_home_by' => Auth::id(),
        ]);

        return response()->json($this->format($medical->fresh('student.user', 'reporter', 'sentHomeBy')));
    }

    /**
     * Toggle doctor visit status
     */
    public function toggleDoctor(MedicalRecord $medical)
    {
        $this->authorize();

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
        $this->authorize();
        $medical->load('student.user', 'student.classRoom', 'reporter', 'recoveredBy', 'sentHomeBy');
        return response()->json($this->format($medical));
    }

    /**
     * Delete a medical record
     */
    public function destroy(MedicalRecord $medical)
    {
        $this->authorize();
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
