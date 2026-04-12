<?php

namespace App\Http\Controllers;

use App\Services\FeeManagementService;
use App\Models\Student;
use App\Models\FeePayment;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeManagementController extends Controller
{
    private FeeManagementService $feeService;

    public function __construct(FeeManagementService $feeService)
    {
        $this->feeService = $feeService;
    }

    /**
     * Get list of students with fee status
     */
    public function getStudents(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $search = $request->input('search', '');
        $statusFilter = $request->input('status', 'all');
        
        $query = Student::with(['user', 'class']);

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Apply search filter
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // OPTIMIZATION: Paginate first, then batch-calculate status for only the visible page
        $paginatedStudents = $query->paginate($perPage, ['*'], 'page', $page);
        $studentIds = collect($paginatedStudents->items())->pluck('id')->toArray();

        // 3 SQL queries total for the entire page (regardless of page size)
        $statusMap = $this->feeService->batchGetStudentSummary($studentIds);

        $studentsWithStatus = collect($paginatedStudents->items())->map(function ($student) use ($statusMap) {
            $s = $statusMap[$student->id] ?? ['total_expected' => 0, 'total_paid' => 0, 'total_pending' => 0, 'status' => 'paid', 'last_payment_date' => null];
            return [
                'id'                => $student->id,
                'name'              => $student->user->name,
                'username'          => $student->username,
                'class_id'          => $student->class_id,
                'class_name'        => $student->class->name ?? '-',
                'total_pending'     => $s['total_pending'],
                'total_paid'        => $s['total_paid'],
                'last_payment_date' => $s['last_payment_date'],
                'status'            => $s['status'],
            ];
        });

        // Apply status filter after batch (only affects in-memory page results)
        if ($statusFilter !== 'all') {
            $studentsWithStatus = $studentsWithStatus->filter(fn($s) => $s['status'] === $statusFilter)->values();
        }

        return response()->json([
            'data'         => $studentsWithStatus->values()->all(),
            'current_page' => $paginatedStudents->currentPage(),
            'per_page'     => $perPage,
            'total'        => $paginatedStudents->total(),
            'last_page'    => $paginatedStudents->lastPage(),
        ]);
    }

    /**
     * Get status counts for all students — single SQL aggregation, no N+1.
     */
    public function getStatusCounts(Request $request)
    {
        $search  = $request->input('search', '');
        $classId = $request->input('class_id');

        $cacheKey = 'fee_status_counts_' . md5($search . '_' . $classId);

        // Cache for 5 minutes — safe, counts not financial data
        return \Cache::remember($cacheKey, 300, function () use ($search, $classId) {
            // Step 1: get matching student IDs (one query)
            $query = Student::select('students.id');
            if ($classId) $query->where('class_id', $classId);
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
                });
            }
            $studentIds = $query->pluck('id')->toArray();

            if (empty($studentIds)) {
                return response()->json(['paid' => 0, 'partial' => 0, 'due' => 0, 'overpaid' => 0]);
            }

            // Step 2: single batch summary (3 queries total)
            $summaries = $this->feeService->batchGetStudentSummary($studentIds);

            $paidCount     = 0;
            $dueCount      = 0;
            $overpaidCount = 0;

            foreach ($summaries as $s) {
                if ($s['status'] === 'overpaid')   $overpaidCount++;
                elseif ($s['status'] === 'paid')   $paidCount++;
                else                               $dueCount++;
            }

            return response()->json([
                'paid'     => $paidCount,
                'partial'  => 0, // not tracked in batch summary
                'due'      => $dueCount,
                'overpaid' => $overpaidCount,
            ]);
        });
    }

    /**
     * Get student fee overview
     */
    public function getStudentOverview($studentId)
    {
        $student = Student::with(['user', 'class'])->findOrFail($studentId);
        $status = $this->feeService->getStudentMonthlyStatus($studentId);
        
        // Filter to include:
        // 1. All months up to NEXT month
        // 2. Future months (after next month) that have payments
        $limitDate = now()->addMonth();
        $limitYear = $limitDate->year;
        $limitMonth = $limitDate->month;

        $statusUpToCurrent = collect($status)->filter(function ($month) use ($limitYear, $limitMonth) {
            // Include if past or next month
            if ($month['year'] < $limitYear) {
                return true;
            }
            if ($month['year'] == $limitYear && $month['month'] <= $limitMonth) {
                return true;
            }
            
            // Include future months ONLY if they have payments
            if ($month['paid'] > 0) {
                return true;
            }
            
            return false;
        })->values()->all();
        
        // Calculate totals ONLY for past and next months
        // We filter the already filtered list again to exclude future months from the sum
        $pastAndCurrentMonths = collect($statusUpToCurrent)->filter(function ($month) use ($limitYear, $limitMonth) {
            if ($month['year'] < $limitYear) {
                return true;
            }
            if ($month['year'] == $limitYear && $month['month'] <= $limitMonth) {
                return true;
            }
            return false;
        });

        $totalExpected = $pastAndCurrentMonths->sum('payable');
        $totalPaid = collect($statusUpToCurrent)->sum('paid');
        $totalPending = $pastAndCurrentMonths->sum('balance');

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->user->name,
                'username' => $student->username,
                'class_name' => $student->class->name,
                'monthly_fee' => (float) $student->monthly_fee,
            ],
            'monthly_status' => array_reverse($statusUpToCurrent), // Reverse to show latest months first
            'total_expected' => $totalExpected,
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
        ]);
    }

    /**
     * Add a payment
     */
    public function addPayment(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'remarks' => 'nullable|string',
            'receipt_issued' => 'nullable|boolean',
        ]);

        try {
            $result = $this->feeService->processPayment(
                $validated['student_id'],
                $validated['amount'],
                $validated['payment_date'],
                $request->user()->id,
                $validated['remarks'] ?? null,
                $validated['receipt_issued'] ?? false
            );

            return response()->json([
                'message' => 'Payment added successfully',
                'payment' => $result['payment'],
                'allocations' => $result['allocations'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment history for a student
     */
    public function getPaymentHistory($studentId)
    {
        $payments = FeePayment::where('student_id', $studentId)
            ->with(['allocations', 'enteredBy'])
            ->latest('payment_date')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->paid_amount,
                    'date' => $payment->payment_date,
                    'receipt_issued' => $payment->receipt_issued,
                    'remarks' => $payment->remarks,
                    'entered_by' => $payment->enteredBy->name,
                    'allocations' => $payment->allocations->map(fn($a) => [
                        'month' => $a->month,
                        'year' => $a->year,
                        'amount' => $a->allocated_amount,
                    ]),
                ];
            });

        return response()->json($payments);
    }

    /**
     * Toggle receipt issued
     */
    public function toggleReceipt($paymentId)
    {
        try {
            $receiptIssued = $this->feeService->toggleReceipt($paymentId);
            return response()->json([
                'message' => 'Receipt status updated',
                'receipt_issued' => $receiptIssued,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Payment not found',
            ], 404);
        }
    }

    /**
     * Set class fee for year
     */
    public function setClassFee(Request $request)
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:class_rooms,id',
            'year' => 'required|integer|min:2020|max:2100',
            'monthly_amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        $count = $this->feeService->setClassFeeForYear(
            $validated['class_id'],
            $validated['year'],
            $validated['monthly_amount'],
            $validated['reason'] ?? 'Standard fee'
        );

        return response()->json([
            'message' => "Created {$count} monthly fee records",
            'count' => $count,
        ]);
    }

    /**
     * Set student fee range
     */
    public function setStudentFeeRange(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'start_year' => 'required|integer',
            'start_month' => 'required|integer|min:1|max:12',
            'end_year' => 'required|integer',
            'end_month' => 'required|integer|min:1|max:12',
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        $count = $this->feeService->setStudentFeeRange(
            $validated['student_id'],
            $validated['start_year'],
            $validated['start_month'],
            $validated['end_year'],
            $validated['end_month'],
            $validated['amount'],
            $validated['reason'] ?? 'Fee adjustment'
        );

        return response()->json([
            'message' => "Updated {$count} monthly fee records",
            'count' => $count,
        ]);
    }

    /**
     * Update student fixed monthly fee
     */
    public function updateStudentMonthlyFee(Request $request, $studentId)
    {
        $validated = $request->validate([
            'monthly_fee' => 'required|numeric|min:0',
        ]);

        $student = Student::findOrFail($studentId);
        $student->monthly_fee = $validated['monthly_fee'];
        $student->save();

        return response()->json([
            'message' => 'Student monthly fee updated successfully',
            'monthly_fee' => $student->monthly_fee,
        ]);
    }

    /**
     * Get overall financial summary
     */
    public function getSummary()
    {
        $summary = $this->feeService->getOverallSummary();
        return response()->json($summary);
    }

    /**
     * Get class-wise report — batch SQL aggregation, no per-student loop.
     */
    public function getClassReport($classId)
    {
        $class    = ClassRoom::findOrFail($classId);
        $students = Student::where('class_id', $classId)
            ->with('user:id,name')
            ->select('id', 'user_id')
            ->get();

        $studentIds = $students->pluck('id')->toArray();

        // 3 queries total via batch — regardless of class size
        $summaries = $this->feeService->batchGetStudentSummary($studentIds);

        // Modal fee per student: most common payable_amount across their plans
        $modalFees = \DB::table('monthly_fee_plans')
            ->select('student_id', 'payable_amount', \DB::raw('COUNT(*) as freq'))
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id', 'payable_amount')
            ->orderByDesc('freq')
            ->get()
            ->groupBy('student_id')
            ->map(fn($rows) => (float) $rows->first()->payable_amount);

        $report = $students->map(function ($student) use ($summaries, $modalFees) {
            $s = $summaries[$student->id] ?? [
                'total_expected' => 0, 'total_paid' => 0, 'total_pending' => 0,
            ];
            return [
                'student_id'      => $student->id,
                'student_name'    => $student->user->name,
                'monthly_payable' => $modalFees[$student->id] ?? 0,
                'total_expected'  => $s['total_expected'],
                'total_paid'      => $s['total_paid'],
                'total_pending'   => $s['total_pending'],
            ];
        });

        return response()->json([
            'class_id'      => $class->id,
            'class_name'    => $class->name,
            'students'      => $report,
            'total_expected' => $report->sum('total_expected'),
            'total_paid'    => $report->sum('total_paid'),
            'total_pending' => $report->sum('total_pending'),
        ]);
    }

    /**
     * Get daily collection report
     */
    public function getDailyReport($date)
    {
        $payments = FeePayment::with(['student.user', 'student.class', 'enteredBy', 'allocations'])
            ->whereDate('payment_date', $date)
            ->latest() // Order by most recent first
            ->get();

        $report = $payments->map(function ($payment) {
            // Format allocations as "Jan(200), Feb(100)"
            $allocations = $payment->allocations->map(function ($allocation) {
                $monthName = date('M', mktime(0, 0, 0, $allocation->month, 10)); // Short month name
                $amount = (float) $allocation->allocated_amount;
                return "{$monthName}({$amount})";
            })->implode(', '); // Join with comma

            return [
                'paymentId' => $payment->id,
                'studentName' => $payment->student->user->name ?? 'Unknown',
                'className' => $payment->student->class->name ?? 'Unknown',
                'amount' => (float) $payment->paid_amount,
                'receiptIssued' => (bool) $payment->receipt_issued,
                'remarks' => $payment->remarks,
                'enteredBy' => $payment->enteredBy->name ?? 'System',
                'time' => $payment->created_at ? $payment->created_at->format('h:i A') : '-', // "10:30 AM"
                'allocations' => $allocations,
            ];
        });

        return response()->json([
            'date' => $date,
            'total_students' => $payments->unique('student_id')->count(),
            'total_amount' => (float) $payments->sum('paid_amount'),
            'payments' => $report,
        ]);
    }

    /**
     * Get available classes
     */
    public function getClasses()
    {
        $classes = ClassRoom::select('id', 'name')->get();
        return response()->json($classes);
    }
}
