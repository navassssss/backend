<?php
// Initialize Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Student;
use App\Models\MonthlyFeePlan;
use Illuminate\Support\Facades\DB;

$students = Student::with('user')->get();
$issuesFound = 0;

echo "Scanning all students for chronological payment gaps...\n";
echo "========================================================\n\n";

foreach ($students as $student) {
    // Get all plans for this student ordered chronologically
    $plans = MonthlyFeePlan::where('student_id', $student->id)
        ->orderBy('year', 'asc')
        ->orderBy('month', 'asc')
        ->get();

    // Get all allocations for this student, grouped by year-month
    $allocations = DB::table('fee_payment_allocations')
        ->where('student_id', $student->id)
        ->select('year', 'month', DB::raw('SUM(allocated_amount) as total_allocated'))
        ->groupBy('year', 'month')
        ->get()
        ->keyBy(function($item) {
            return $item->year . '-' . $item->month;
        });

    $hasUnpaidEarlierMonth = false;
    $earliestUnpaidName = "";
    $gapFound = false;

    foreach ($plans as $plan) {
        $key = $plan->year . '-' . $plan->month;
        $allocated = isset($allocations[$key]) ? (float)$allocations[$key]->total_allocated : 0;
        $expected = (float)$plan->payable_amount;
        
        $balance = $expected - $allocated;
        $monthName = date("F Y", mktime(0, 0, 0, $plan->month, 10, $plan->year));

        // If this month has a balance (unpaid or partially paid)
        if ($balance > 0) {
            if (!$hasUnpaidEarlierMonth) {
                $hasUnpaidEarlierMonth = true;
                $earliestUnpaidName = $monthName . " (Expected: {$expected}, Paid: {$allocated})";
            }
        } 
        // If this month has some allocation, AND we already saw an unpaid earlier month
        elseif ($allocated > 0 && $hasUnpaidEarlierMonth) {
            echo "ISSUE FOUND: Roll No: {$student->roll_number} | Name: " . ($student->user->name ?? 'Unknown') . "\n";
            echo "  -> EARLIER UNPAID: {$earliestUnpaidName}\n";
            echo "  -> BUT LATER PAID: {$monthName} (Paid: {$allocated})\n";
            echo "--------------------------------------------------------\n";
            $gapFound = true;
            $issuesFound++;
            break; // Move to next student
        }
    }
}

if ($issuesFound === 0) {
    echo "Great news! No chronological gaps found in the database.\n";
} else {
    echo "\nTotal Students with payment gaps: {$issuesFound}\n";
    echo "You can now safely run the migration to fix these!\n";
}
