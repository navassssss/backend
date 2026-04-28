<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOutpassRequest;
use App\Http\Requests\CheckinOutpassRequest;
use App\Models\Outpass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutpassController extends Controller
{
    public function dashboard(): JsonResponse
    {
        // Single query per stat — fast counts via DB indexes
        return response()->json([
            'total_today'       => Outpass::whereDate('out_time', today())->count(),
            'currently_outside' => Outpass::outside()->count(),
            'overdue'           => Outpass::overdue()->count(),
            'returned_today'    => Outpass::returned()->whereDate('actual_in_time', today())->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Outpass::with([
                'student:id,user_id,class_id,roll_number',
                'student.user:id,name',
                'student.classRoom:id,name',
                'creator:id,name',
            ])
            ->orderBy('out_time', 'desc');

        // Use filled() — guards against empty string / 'all' being passed
        if ($request->filled('status')) {
            match ($request->string('status')->value()) {
                'outside'  => $query->outside(),
                'returned' => $query->returned(),
                'overdue'  => $query->overdue(),
                default    => null,
            };
        }

        if ($request->filled('class_id')) {
            $classId = $request->integer('class_id');
            $query->whereHas('student', fn ($q) => $q->where('class_id', $classId));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('out_time', $request->date('date'));
        }

        return response()->json($query->paginate(20));
    }

    public function store(StoreOutpassRequest $request): JsonResponse
    {
        $outpass = Outpass::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Outpass created successfully',
            'outpass' => $outpass->load(['student.user', 'student.classRoom', 'creator']),
        ], 201);
    }

    public function checkin(Outpass $outpass, CheckinOutpassRequest $request): JsonResponse
    {
        // Guard: prevent double check-in
        if ($outpass->actual_in_time !== null) {
            return response()->json(['message' => 'Student has already been checked in.'], 422);
        }

        $outpass->update([
            'actual_in_time' => now(),
        ]);

        return response()->json([
            'message' => 'Student checked in successfully',
            'outpass' => $outpass->load(['student.user', 'student.classRoom', 'creator']),
        ]);
    }
}
