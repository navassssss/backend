<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FeeDataImportSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '512M');
        
        $csvPath = storage_path('app/fee_data.csv');
        
        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found at: {$csvPath}");
            return;
        }

        $this->command->info("Reading fee data from CSV...");
        
        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header
        
        $processed = 0;
        $notFound = [];
        $errors = [];
        $row = 0;
        
        while (($data = fgetcsv($file)) !== false) {
            $row++;
            
            if ($row % 10 == 0) {
                $this->command->info("Row {$row}...");
            }
            
            try {
                DB::beginTransaction();
                
                $username = trim($data[0] ?? '');
                if (empty($username)) {
                    DB::commit();
                    continue;
                }
                
                $student = Student::where('username', $username)->first();
                if (!$student) {
                    $notFound[] = $username;
                    DB::commit();
                    continue;
                }
                
                // Parse dates with explicit year handling
                $dateFrom = trim($data[1] ?? '');
                $dateUpto = trim($data[2] ?? '');
                $monthlyAmount = (float) ($data[3] ?? 0);
                $totalPaid = (float) ($data[9] ?? 0);
                
                // Parse start date
                if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $dateFrom, $m)) {
                    $day = (int)$m[1];
                    $month = (int)$m[2];
                    $year = (int)$m[3];
                    // Convert 2-digit year to 4-digit (25 -> 2025)
                    if ($year < 100) $year += 2000;
                    $startDate = Carbon::create($year, $month, $day);
                } else {
                    $errors[] = "Bad date format for {$username}: {$dateFrom}";
                    DB::commit();
                    continue;
                }
                
                // Parse end date
                if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $dateUpto, $m)) {
                    $day = (int)$m[1];
                    $month = (int)$m[2];
                    $year = (int)$m[3];
                    if ($year < 100) $year += 2000;
                    $endDate = Carbon::create($year, $month, $day);
                } else {
                    $errors[] = "Bad date format for {$username}: {$dateUpto}";
                    DB::commit();
                    continue;
                }
                
                // Sanity check: don't create more than 24 months
                $monthsDiff = $startDate->diffInMonths($endDate);
                if ($monthsDiff > 60) {
                    $errors[] = "Date range too large for {$username}: {$monthsDiff} months";
                    DB::commit();
                    continue;
                }
                
                // Create monthly fee plans
                $current = $startDate->copy();
                while ($current <= $endDate) {
                    DB::table('monthly_fee_plans')->updateOrInsert(
                        [
                            'student_id' => $student->id,
                            'year' => $current->year,
                            'month' => $current->month,
                        ],
                        [
                            'payable_amount' => $monthlyAmount,
                            'set_by' => 1,
                            'reason' => 'Imported',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    $current->addMonth();
                }
                
                // Create payment if exists
                if ($totalPaid > 0) {
                    $paymentId = DB::table('fee_payments')->insertGetId([
                        'student_id' => $student->id,
                        'paid_amount' => $totalPaid,
                        'payment_date' => $startDate->format('Y-m-d'),
                        'receipt_issued' => 0,
                        'entered_by' => 1,
                        'remarks' => 'Imported',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Allocate payment
                    $remaining = $totalPaid;
                    $allocDate = $startDate->copy();
                    
                    while ($remaining > 0 && $allocDate <= $endDate) {
                        $amount = min($remaining, $monthlyAmount);
                        
                        DB::table('fee_payment_allocations')->insert([
                            'fee_payment_id' => $paymentId,
                            'student_id' => $student->id,
                            'year' => $allocDate->year,
                            'month' => $allocDate->month,
                            'allocated_amount' => $amount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        $remaining -= $amount;
                        $allocDate->addMonth();
                    }
                }
                
                DB::commit();
                $processed++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = "Error row {$row}: " . $e->getMessage();
            }
        }
        
        fclose($file);
        
        $this->command->info("✅ Processed: {$processed}");
        if (count($notFound) > 0) {
            $this->command->warn("⚠️  Not found: " . count($notFound));
        }
        if (count($errors) > 0) {
            $this->command->error("❌ Errors: " . count($errors));
            foreach (array_slice($errors, 0, 5) as $err) {
                $this->command->error("  {$err}");
            }
        }
    }
}
