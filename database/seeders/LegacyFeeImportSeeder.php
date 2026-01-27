<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\MonthlyFeePlan;
use App\Models\FeePayment;
use App\Services\FeeManagementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyFeeImportSeeder extends Seeder
{
    private $feeService;

    public function __construct(FeeManagementService $feeService)
    {
        $this->feeService = $feeService;
    }

    public function run()
    {
        $filePath = public_path('monthly.csv');
        if (!file_exists($filePath)) {
            $this->command->error("CSV file not found at: {$filePath}");
            return;
        }

        $this->command->info("Reading CSV from: {$filePath}");

        // Open CSV file
        $file = fopen($filePath, 'r');
        
        // Skip header row if exists
        $header = fgetcsv($file); 

        DB::beginTransaction();

        try {
            $processed = 0;
            $skipped = 0;

            while (($row = fgetcsv($file)) !== false) {
                 // Column Expected structure based on image:
                 // 0: AD.NO & NAME (e.g., "791 MUHAMMED FALAH")
                 // 1: CLASS
                 // 2: MONTHLY
                 // 3: REMAINING

                $adNoName = $row[0] ?? '';
                $monthlyStr = $row[2] ?? 0;
                $remainingStr = $row[3] ?? 0; // The Remaining column is index 3

                // Extract Ad No (first numeric part)
                // "791 MUHAMMED..." -> 791
                if (!preg_match('/^(\d+)/', trim($adNoName), $matches)) {
                    // Try to find ANY number if not at start, or just skip
                    // $this->command->warn("  Skipping row (No Ad No found): " . $adNoName);
                    // $skipped++;
                    continue; 
                }
                $rollNo = $matches[1];

                // Parse Amounts
                // Handle "NONE", empty, or text as 0
                $monthlyFee = $this->parseAmount($monthlyStr);
                $totalRemaining = $this->parseAmount($remainingStr);

                $student = Student::where('roll_number', $rollNo)->first();

                if (!$student) {
                    $this->command->warn("  Student with Roll No {$rollNo} not found. Skipping.");
                    $skipped++;
                    continue;
                }

                $this->command->info("Processing {$student->user->name} ({$rollNo})...");

                // 1. Update Student's Fixed Monthly Fee
                $student->monthly_fee = $monthlyFee;
                $student->save();

                // 2. Ensure Plans for Jan 2026 and Feb 2026 Exists
                // We use updateOrCreate to ensure we don't duplicate if run multiple times
                // If Monthly Fee is 0, we might still want to create the plan as 0 or skip?
                // Let's create it as 0 to be consistent with the "Standard"
                $planJan = MonthlyFeePlan::updateOrCreate(
                    ['student_id' => $student->id, 'year' => 2026, 'month' => 1],
                    ['payable_amount' => $monthlyFee, 'reason' => 'Standard Fee (Legacy Import)']
                );

                $planFeb = MonthlyFeePlan::updateOrCreate(
                    ['student_id' => $student->id, 'year' => 2026, 'month' => 2],
                    ['payable_amount' => $monthlyFee, 'reason' => 'Standard Fee (Legacy Import)']
                );

                // 3. Calculate Logic
                // Total expected for Jan & Feb
                $janFebTotal = $monthlyFee * 2;
                
                // Difference between what they SHOULD owe for Jan+Feb and what they ACTUALLY owe
                $diff = $totalRemaining - $janFebTotal;

                if ($diff > 0) {
                    // SCENARIO: They owe MORE than just Jan+Feb.
                    // This means they have previous year backlog.
                    // We create a "Previous Year Balance" plan for Dec 2025.
                    
                    MonthlyFeePlan::updateOrCreate(
                        ['student_id' => $student->id, 'year' => 2025, 'month' => 12],
                        [
                            'payable_amount' => $diff,
                            'reason' => 'Previous Year Remaining Balance'
                        ]
                    );

                    $this->command->line("  -> Added Previous Year Balance: {$diff}");

                } elseif ($diff < 0) {
                    // SCENARIO: They owe LESS than Jan+Feb.
                    // This means they have already paid some of Jan or Feb.
                    // We need to create a "Legacy Payment" to clear the difference.
                    
                    $paymentAmount = abs($diff);
                    
                    // Only process payment if amount > 0
                    if ($paymentAmount > 0) {
                        try {
                            $this->feeService->processPayment(
                                $student->id,
                                $paymentAmount,
                                '2026-01-01', // Date of import
                                1, // Admin ID (system)
                                'Legacy Balance Adjustment',
                                false
                            );
                            $this->command->line("  -> Created Adjustment Payment: {$paymentAmount}");
                        } catch (\Exception $e) {
                             $this->command->error("     Failed to create payment: " . $e->getMessage());
                        }
                    }
                } else {
                     $this->command->line("  -> Balance matches exactly (Jan+Feb). No action needed.");
                }
                
                $processed++;
            }

            fclose($file);
            DB::commit();
            $this->command->info("Legacy Import Completed. Processed: {$processed}, Skipped: {$skipped}");

        } catch (\Exception $e) {
            fclose($file);
            DB::rollBack();
            $this->command->error("Error: " . $e->getMessage());
        }
    }

    private function parseAmount($value) {
        if (empty($value)) return 0;
        $clean = preg_replace('/[^0-9.]/', '', strtolower(trim($value)));
        if (empty($clean) || strtolower(trim($value)) === 'none') return 0;
        return (float)$clean;
    }
}
