<?php

namespace App\Services;

use App\Models\MonthlyFeePlan;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class FeeManagementService
{
    /**
     * Process a payment with auto-clearing logic
     */
    public function processPayment(
        int $studentId,
        float $amount,
        string $paymentDate,
        int $enteredBy,
        ?string $remarks = null,
        bool $receiptIssued = false
    ): array {
        DB::beginTransaction();
        
        try {
            // 1. Create payment record
            $payment = FeePayment::create([
                'student_id' => $studentId,
                'paid_amount' => $amount,
                'payment_date' => $paymentDate,
                'entered_by' => $enteredBy,
                'remarks' => $remarks,
                'receipt_issued' => $receiptIssued,
            ]);
            
            // 2. Get unpaid months (oldest first)
            $unpaidMonths = $this->getUnpaidMonths($studentId);
            
            // 3. Allocate payment to unpaid months
            $remaining = $amount;
            $allocations = [];
            
            foreach ($unpaidMonths as $monthData) {
                if ($remaining <= 0) break;
                
                $balance = $monthData['balance'];
                $allocateAmount = min($remaining, $balance);
                
                FeePaymentAllocation::create([
                    'fee_payment_id' => $payment->id,
                    'student_id' => $studentId,
                    'year' => $monthData['year'],
                    'month' => $monthData['month'],
                    'allocated_amount' => $allocateAmount,
                ]);
                
                $allocations[] = [
                    'month' => $monthData['month'],
                    'year' => $monthData['year'],
                    'allocated' => $allocateAmount,
                    'cleared' => ($allocateAmount >= $balance),
                    'auto_created' => false,
                ];
                
                $remaining -= $allocateAmount;
            }
            
            // 4. Handle overpayment (auto-create future months)
            if ($remaining > 0) {
                $futureAllocations = $this->allocateToFutureMonths(
                    $studentId,
                    $payment->id,
                    $remaining
                );
                $allocations = array_merge($allocations, $futureAllocations);
            }
            
            DB::commit();
            
            return [
                'payment' => $payment,
                'allocations' => $allocations,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Batch fetch monthly status for multiple students in ONE SQL query.
     * Returns a map keyed by student_id:
     *   [student_id => ['paid' => X, 'expected' => Y, 'pending' => Z, 'status' => 'paid|due|overpaid']]
     *
     * This replaces the N+1 per-student getStudentMonthlyStatus() loop pattern.
     */
    public function batchGetStudentSummary(array $studentIds, ?int $limitYear = null, ?int $limitMonth = null): array
    {
        if (empty($studentIds)) return [];

        $limitYear  = $limitYear  ?? now()->addMonth()->year;
        $limitMonth = $limitMonth ?? now()->addMonth()->month;

        // Single query: total expected per student up to limit date
        $expectedRaw = DB::table('monthly_fee_plans')
            ->select('student_id', DB::raw('SUM(payable_amount) as total_expected'))
            ->whereIn('student_id', $studentIds)
            ->where(function ($q) use ($limitYear, $limitMonth) {
                $q->where('year', '<', $limitYear)
                  ->orWhere(function ($q2) use ($limitYear, $limitMonth) {
                      $q2->where('year', $limitYear)->where('month', '<=', $limitMonth);
                  });
            })
            ->groupBy('student_id')
            ->pluck('total_expected', 'student_id');

        // Single query: total paid per student (all time)
        $paidRaw = DB::table('fee_payment_allocations')
            ->select('student_id', DB::raw('SUM(allocated_amount) as total_paid'))
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id')
            ->pluck('total_paid', 'student_id');

        // Last payment per student (one query with MAX)
        $lastPayments = DB::table('fee_payments')
            ->select('student_id', DB::raw('MAX(payment_date) as last_payment_date'))
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id')
            ->pluck('last_payment_date', 'student_id');

        $result = [];
        foreach ($studentIds as $sid) {
            $expected = (float) ($expectedRaw[$sid] ?? 0);
            $paid     = (float) ($paidRaw[$sid] ?? 0);
            $pending  = $expected - $paid;

            $status = 'paid';
            if ($pending > 0)  $status = 'due';
            elseif ($pending < 0) $status = 'overpaid';

            $result[$sid] = [
                'total_expected'    => $expected,
                'total_paid'        => $paid,
                'total_pending'     => $pending,
                'status'            => $status,
                'last_payment_date' => $lastPayments[$sid] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Get unpaid months for a student (oldest first) — optimized with one preloaded allocation map.
     */
    private function getUnpaidMonths(int $studentId): array
    {
        $plans = MonthlyFeePlan::where('student_id', $studentId)
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Preload all allocations for this student in one query
        $paidMap = DB::table('fee_payment_allocations')
            ->where('student_id', $studentId)
            ->select('year', 'month', DB::raw('SUM(allocated_amount) as paid'))
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn($r) => "{$r->year}-{$r->month}");

        $unpaidMonths = [];

        foreach ($plans as $plan) {
            $key       = "{$plan->year}-{$plan->month}";
            $paidAmount = (float) ($paidMap[$key]->paid ?? 0);
            $balance    = $plan->payable_amount - $paidAmount;

            if ($balance > 0) {
                $unpaidMonths[] = [
                    'year'     => $plan->year,
                    'month'    => $plan->month,
                    'expected' => $plan->payable_amount,
                    'paid'     => $paidAmount,
                    'balance'  => $balance,
                ];
            }
        }

        return $unpaidMonths;
    }

    /**
     * Allocate overpayment to future months (auto-create if needed)
     */
    private function allocateToFutureMonths(
        int $studentId,
        int $paymentId,
        float $remaining
    ): array {
        $student = Student::find($studentId);
        $defaultMonthlyFee = $student ? (float)$student->monthly_fee : 0.0;

        // Get latest fee plan
        $latestPlan = MonthlyFeePlan::where('student_id', $studentId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();
        
        // If no fee plan exists, create a default one for current month
        if (!$latestPlan) {
            $currentDate = now();
            // Determine initial fee: Use default if set, otherwise use payment amount
            $initialFee = ($defaultMonthlyFee > 0) ? $defaultMonthlyFee : $remaining;

            $latestPlan = MonthlyFeePlan::create([
                'student_id' => $studentId,
                'year' => $currentDate->year,
                'month' => $currentDate->month,
                'payable_amount' => $initialFee,
                'reason' => 'Auto-created during payment (no existing plan)',
            ]);
        }
        
        // Determine the standard amount for future months
        // If student has a fixed monthly fee, use it. Otherwise propagate the last month's fee.
        $standardAmount = ($defaultMonthlyFee > 0) ? $defaultMonthlyFee : $latestPlan->payable_amount;
        
        // SAFETY: If student has ₹0 monthly fee and no history, allocate entire remaining to current month
        if ($standardAmount <= 0) {
            $currentDate = now();
            $currentYear = $currentDate->year;
            $currentMonth = $currentDate->month;
            
            // Create fee plan for current month with the payment amount
            MonthlyFeePlan::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'year' => $currentYear,
                    'month' => $currentMonth,
                ],
                [
                    'payable_amount' => $remaining,
                    'reason' => 'Payment for zero-fee student',
                ]
            );
            
            // Allocate entire payment to current month
            FeePaymentAllocation::create([
                'fee_payment_id' => $paymentId,
                'student_id' => $studentId,
                'year' => $currentYear,
                'month' => $currentMonth,
                'allocated_amount' => $remaining,
            ]);
            
            return [[
                'month' => $currentMonth,
                'year' => $currentYear,
                'allocated' => $remaining,
                'cleared' => true,
                'auto_created' => true,
            ]];
        }
        
        $currentYear = $latestPlan->year;
        $currentMonth = $latestPlan->month;
        
        $allocations = [];
        $maxIterations = 120; // Safety limit: max 10 years
        $iterations = 0;
        
        while ($remaining > 0 && $iterations < $maxIterations) {
            $iterations++;
            
            // Move to next month
            $currentMonth++;
            if ($currentMonth > 12) {
                $currentMonth = 1;
                $currentYear++;
            }
            
            // Create/update fee plan for this month (using updateOrCreate to avoid duplicates)
            // Use standardAmount (which honors monthly_fee)
            MonthlyFeePlan::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'year' => $currentYear,
                    'month' => $currentMonth,
                ],
                [
                    'payable_amount' => $standardAmount,
                    'reason' => 'Auto-created during payment',
                ]
            );
            
            // Allocate payment
            // We compare against standardAmount (the fee for this month)
            $allocateAmount = min($remaining, $standardAmount);
            
            FeePaymentAllocation::create([
                'fee_payment_id' => $paymentId,
                'student_id' => $studentId,
                'year' => $currentYear,
                'month' => $currentMonth,
                'allocated_amount' => $allocateAmount,
            ]);
            
            $allocations[] = [
                'month' => $currentMonth,
                'year' => $currentYear,
                'allocated' => $allocateAmount,
                'cleared' => ($allocateAmount >= $standardAmount),
                'auto_created' => true,
            ];
            
            $remaining -= $allocateAmount;
        }
        
        if ($iterations >= $maxIterations) {
            throw new \Exception('Payment allocation exceeded maximum iterations');
        }
        
        return $allocations;
    }

    /**
     * Set fee for a class for entire year
     */
    public function setClassFeeForYear(
        int $classId,
        int $year,
        float $monthlyAmount,
        string $reason = 'Standard fee'
    ): int {
        $students = Student::where('class_id', $classId)->get();
        $count = 0;
        
        foreach ($students as $student) {
            for ($month = 1; $month <= 12; $month++) {
                MonthlyFeePlan::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'payable_amount' => $monthlyAmount,
                        'reason' => $reason,
                    ]
                );
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Set fee for a student for a date range
     */
    public function setStudentFeeRange(
        int $studentId,
        int $startYear,
        int $startMonth,
        int $endYear,
        int $endMonth,
        float $amount,
        string $reason
    ): int {
        $count = 0;
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $monthStart = ($year == $startYear) ? $startMonth : 1;
            $monthEnd = ($year == $endYear) ? $endMonth : 12;
            
            for ($month = $monthStart; $month <= $monthEnd; $month++) {
                MonthlyFeePlan::updateOrCreate(
                    ['student_id' => $studentId, 'year' => $year, 'month' => $month],
                    ['payable_amount' => $amount, 'reason' => $reason]
                );
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Get student monthly status (derived, not stored)
     */
    public function getStudentMonthlyStatus(int $studentId): array
    {
        $plans = MonthlyFeePlan::where('student_id', $studentId)
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        
        $status = [];
        
        foreach ($plans as $plan) {
            $paidAmount = FeePaymentAllocation::where('student_id', $studentId)
                ->where('year', $plan->year)
                ->where('month', $plan->month)
                ->sum('allocated_amount');
            
            $balance = $plan->payable_amount - $paidAmount;
            
            $monthStatus = 'unpaid';
            if ($balance <= 0) $monthStatus = 'paid';
            elseif ($paidAmount > 0) $monthStatus = 'partial';
            
            $status[] = [
                'year' => $plan->year,
                'month' => $plan->month,
                'month_name' => date('F Y', mktime(0, 0, 0, $plan->month, 1, $plan->year)),
                'payable' => (float) $plan->payable_amount,
                'paid' => (float) $paidAmount,
                'balance' => max(0, $balance),
                'status' => $monthStatus,
            ];
        }
        
        // IMPORTANT: Ensure current month is always included
        $currentYear = now()->year;
        $currentMonth = now()->month;
        
        // Check if current month already exists in status
        $currentMonthExists = collect($status)->contains(function ($item) use ($currentYear, $currentMonth) {
            return $item['year'] == $currentYear && $item['month'] == $currentMonth;
        });
        
        // If current month doesn't exist, add it with expected amount from latest fee plan
        if (!$currentMonthExists) {
            // Get the most recent fee plan to determine expected amount
            $latestPlan = MonthlyFeePlan::where('student_id', $studentId)
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->first();
            
            // Use latest plan amount, or 0 if no plan exists
            $expectedAmount = $latestPlan ? (float) $latestPlan->payable_amount : 0.0;
            
            // Check if there are any payments for current month
            $currentMonthPaid = FeePaymentAllocation::where('student_id', $studentId)
                ->where('year', $currentYear)
                ->where('month', $currentMonth)
                ->sum('allocated_amount');
            
            $balance = max(0, $expectedAmount - $currentMonthPaid);
            
            $monthStatus = 'unpaid';
            if ($balance <= 0) $monthStatus = 'paid';
            elseif ($currentMonthPaid > 0) $monthStatus = 'partial';
            
            $status[] = [
                'year' => $currentYear,
                'month' => $currentMonth,
                'month_name' => date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)),
                'payable' => $expectedAmount,
                'paid' => (float) $currentMonthPaid,
                'balance' => $balance,
                'status' => $monthStatus,
            ];
            
            // Re-sort the status array by year and month
            usort($status, function ($a, $b) {
                if ($a['year'] != $b['year']) {
                    return $a['year'] - $b['year'];
                }
                return $a['month'] - $b['month'];
            });
        }
        
        return $status;
    }

    /**
     * Get overall summary till today
     */
    public function getOverallSummary(): array
    {
        $today = now();
        $currentYear = $today->year;
        $currentMonth = $today->month;
        
        // Total expected till today
        $totalExpected = MonthlyFeePlan::where(function($q) use ($currentYear, $currentMonth) {
            $q->where('year', '<', $currentYear)
              ->orWhere(function($q2) use ($currentYear, $currentMonth) {
                  $q2->where('year', $currentYear)
                     ->where('month', '<=', $currentMonth);
              });
        })->sum('payable_amount');
        
        // Total paid (all time)
        $totalPaid = FeePaymentAllocation::sum('allocated_amount');
        
        return [
            'total_expected' => (float) $totalExpected,
            'total_paid' => (float) $totalPaid,
            'total_pending' => (float) ($totalExpected - $totalPaid),
            'collection_percentage' => $totalExpected > 0 ? round(($totalPaid / $totalExpected) * 100, 2) : 0,
        ];
    }

    /**
     * Toggle receipt issued status
     */
    public function toggleReceipt(int $paymentId): bool
    {
        $payment = FeePayment::findOrFail($paymentId);
        $payment->receipt_issued = !$payment->receipt_issued;
        $payment->save();
        
        return $payment->receipt_issued;
    }
}
