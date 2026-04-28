<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        
        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        // Get all attendance records for this student
        $records = AttendanceRecord::where('student_id', $student->id)
            ->with(['attendance.classRoom'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($record) {
                return [
                    'id' => $record->id,
                    'date' => $record->attendance->date,
                    'session' => $record->attendance->session,
                    'status' => $record->status,
                    'className' => $record->attendance->classRoom->name ?? 'Unknown',
                    'submittedAt' => $record->created_at->toIso8601String()
                ];
            });

        // Calculate stats
        $totalRecords = $records->count();
        $presentCount = $records->where('status', 'present')->count();
        $absentCount = $records->where('status', 'absent')->count();
        $percentage = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0;

        // Get monthly breakdown (last 6 months)
        $monthlyStats = AttendanceRecord::where('student_id', $student->id)
            ->join('attendances', 'attendance_records.attendance_id', '=', 'attendances.id')
            ->select(
                DB::raw('strftime("%Y-%m", attendances.date) as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN attendance_records.status = "present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN attendance_records.status = "absent" THEN 1 ELSE 0 END) as absent')
            )
            ->where('attendances.date', '>=', now()->subMonths(6)->format('Y-m-d'))
            ->groupBy(DB::raw('strftime("%Y-%m", attendances.date)'))
            ->orderBy('month', 'desc')
            ->get()
            ->map(function($stat) {
                $percentage = $stat->total > 0 ? round(($stat->present / $stat->total) * 100, 2) : 0;
                return [
                    'month' => $stat->month,
                    'total' => $stat->total,
                    'present' => $stat->present,
                    'absent' => $stat->absent,
                    'percentage' => $percentage
                ];
            });

        return response()->json([
            'stats' => [
                'total' => $totalRecords,
                'present' => $presentCount,
                'absent' => $absentCount,
                'percentage' => $percentage
            ],
            'monthlyStats' => $monthlyStats,
            'records' => $records->take(50) // Limit to recent 50 records
        ]);
    }
}
