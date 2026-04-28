<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\MonthlyFeePlan;
use App\Services\FeeManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LegacyFeeImportController extends Controller
{
    private $feeService;

    public function __construct(FeeManagementService $feeService)
    {
        $this->feeService = $feeService;
    }

    public function import()
    {
        $filePath = database_path('seeders/monthly.csv');
        if (!file_exists($filePath)) {
            return response()->json(['error' => "CSV file not found at: {$filePath}"], 404);
        }

        $file = fopen($filePath, 'r');
        
        // Skip header row if exists
        $header = fgetcsv($file);

        $logs = [
            'processed' => [],
            'skipped' => [],
            'zero_balance' => [],
            'errors' => []
        ];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($file)) !== false) {
                 // Column Expected structure based on legacy:
                 // 0: AD.NO & NAME (e.g., "791 MUHAMMED FALAH")
                 // 1: CLASS
                 // 2: MONTHLY
                 // 3: REMAINING

                $adNoName = $row[0] ?? '';
                $monthlyStr = $row[2] ?? 0;
                $remainingStr = $row[3] ?? 0;

                // Extract Ad No (first part, can be H123 or 791)
                if (!preg_match('/^([Hh]?\d+)/', trim($adNoName), $matches)) {
                    continue; 
                }
                $rollNo = strtoupper($matches[1]);

                $monthlyFee = $this->parseAmount($monthlyStr);
                $totalRemaining = $this->parseAmount($remainingStr);

                $student = Student::where('roll_number', $rollNo)->first();

                if (!$student) {
                    $logs['skipped'][] = [
                        'roll_no' => $rollNo,
                        'reason' => 'Student not found in database.',
                        'raw_data' => $row
                    ];
                    continue;
                }

                // 1. Update Student's Fixed Monthly Fee
                $student->monthly_fee = $monthlyFee;
                $student->save();

                // 2. Base Validation
                if ($totalRemaining < 0) {
                    $logs['skipped'][] = [
                        'roll_no' => $rollNo,
                        'name' => $student->user->name,
                        'reason' => "Negative remaining balance: {$totalRemaining}"
                    ];
                    continue;
                }

                if ($totalRemaining == 0) {
                    $logs['zero_balance'][] = [
                        'roll_no' => $rollNo,
                        'name' => $student->user->name,
                        'monthly_fee_set' => $monthlyFee
                    ];
                    continue;
                }

                if ($monthlyFee <= 0) {
                    $logs['skipped'][] = [
                        'roll_no' => $rollNo,
                        'name' => $student->user->name,
                        'reason' => "Monthly fee is <= 0 but remaining balance is {$totalRemaining}. Cannot determine months."
                    ];
                    continue;
                }

                // 3. Dynamic Month Generation (Starting April 2026 backwards)
                $fullMonths = floor($totalRemaining / $monthlyFee);
                // Safe way to get remainder ignoring minor float precision issues
                $remainder = round($totalRemaining - ($fullMonths * $monthlyFee), 2);

                $currentDate = Carbon::create(2026, 4, 1);
                $generatedPlans = [];

                // Create full month plans
                for ($i = 0; $i < $fullMonths; $i++) {
                    $plan = MonthlyFeePlan::updateOrCreate(
                        ['student_id' => $student->id, 'year' => $currentDate->year, 'month' => $currentDate->month],
                        ['payable_amount' => $monthlyFee, 'reason' => 'Standard Fee (Legacy Import)']
                    );
                    
                    $generatedPlans[] = [
                        'month' => $currentDate->month,
                        'year' => $currentDate->year,
                        'amount' => $monthlyFee
                    ];

                    $currentDate->subMonthNoOverflow();
                }

                // Create partial remainder plan
                if ($remainder > 0) {
                    $plan = MonthlyFeePlan::updateOrCreate(
                        ['student_id' => $student->id, 'year' => $currentDate->year, 'month' => $currentDate->month],
                        ['payable_amount' => $remainder, 'reason' => 'Previous Balance Remainder (Legacy Import)']
                    );
                    
                    $generatedPlans[] = [
                        'month' => $currentDate->month,
                        'year' => $currentDate->year,
                        'amount' => $remainder
                    ];
                }

                $logs['processed'][] = [
                    'roll_no' => $rollNo,
                    'name' => $student->user->name,
                    'monthly_fee' => $monthlyFee,
                    'total_remaining' => $totalRemaining,
                    'generated_plans' => $generatedPlans
                ];
            }

            fclose($file);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Legacy fee import completed successfully.',
                'summary' => [
                    'processed_count' => count($logs['processed']),
                    'skipped_count' => count($logs['skipped']),
                    'zero_balance_count' => count($logs['zero_balance']),
                    'error_count' => count($logs['errors']),
                ],
                'details' => $logs
            ]);

        } catch (\Exception $e) {
            fclose($file);
            DB::rollBack();
            Log::error('LegacyFeeImport Error: ' . $e->getMessage());
            
            $logs['errors'][] = $e->getMessage();
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during import. Transaction rolled back.',
                'logs' => $logs
            ], 500);
        }
    }

    private function parseAmount($value) {
        if (empty($value)) return 0;
        $clean = preg_replace('/[^0-9.]/', '', strtolower(trim($value)));
        if (empty($clean) || strtolower(trim($value)) === 'none') return 0;
        return (float)$clean;
    }
}
