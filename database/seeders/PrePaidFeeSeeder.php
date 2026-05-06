<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\MonthlyFeePlan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrePaidFeeSeeder extends Seeder
{
    public function run()
    {
        $studentsData = [
            ['roll_number' => '435', 'name' => 'MUHAMMED ASNAF KV', 'amount' => 200, 'end_month' => 'MAY', 'end_year' => 2026],
            ['roll_number' => '746', 'name' => 'MUHAMMAD SALMAN', 'amount' => 250, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '796', 'name' => 'MUHAMMED FATHEEN MK', 'amount' => 500, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '488', 'name' => 'MUHAMMED RISHAN PP', 'amount' => 1000, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '515', 'name' => 'MUHAMMED K C', 'amount' => 500, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '529', 'name' => 'S. M ABOOBACKER SHUJAH S A', 'amount' => 100, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '734', 'name' => 'AHNAF ABDULLA N A', 'amount' => 500, 'end_month' => 'JUN', 'end_year' => 2026],
            ['roll_number' => '505', 'name' => 'MUHAMMED SABIQUE C', 'amount' => 500, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '645', 'name' => 'MUHAMMED RAZAL PKT', 'amount' => 500, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '648', 'name' => 'MUHAMMED SHAFI A K', 'amount' => 300, 'end_month' => 'MAY', 'end_year' => 2026],
            ['roll_number' => '663', 'name' => 'MUHAMMED SHAREEF', 'amount' => 1000, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '667', 'name' => 'MUHAMMED RAZIN KC', 'amount' => 100, 'end_month' => 'FEB', 'end_year' => 2027],
            ['roll_number' => '684', 'name' => 'MUHAMMED RAZEEN PP', 'amount' => 500, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '696', 'name' => 'FUAD HABEEB', 'amount' => 500, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '725', 'name' => 'SAYED BURHAN SHABI', 'amount' => 500, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '759', 'name' => 'MUHAMMED NAJAH KC', 'amount' => 500, 'end_month' => 'APR', 'end_year' => 2026],
            ['roll_number' => '783', 'name' => 'MUHAMMED ZIYAN K', 'amount' => 250, 'end_month' => 'OCT', 'end_year' => 2026],
            ['roll_number' => '833', 'name' => 'MUHAMMED SHIBILI', 'amount' => 200, 'end_month' => 'APR', 'end_year' => 2026],
        ];

        DB::beginTransaction();
        try {
            foreach ($studentsData as $data) {
                $student = Student::where('roll_number', $data['roll_number'])->first();
                
                if (!$student) {
                    $this->command->warn("Student {$data['roll_number']} ({$data['name']}) not found in database. Skipping.");
                    continue;
                }

                // 1. Update Fixed Monthly Fee
                $student->monthly_fee = $data['amount'];
                $student->save();

                // 2. Determine Date Range
                $startMonth = 3; // March
                $startYear = 2026;
                $endMonthNum = Carbon::parse($data['end_month'])->month;
                $endYear = $data['end_year'];

                $cursor = Carbon::create($startYear, $startMonth, 1);
                $endDate = Carbon::create($endYear, $endMonthNum, 1);

                $monthsToPay = [];
                while ($cursor <= $endDate) {
                    $monthsToPay[] = [
                        'year' => $cursor->year,
                        'month' => $cursor->month,
                    ];
                    $cursor->addMonth();
                }
                
                // Scalar fix
                foreach($monthsToPay as &$m) {
                    $m['year'] = (int) $m['year'];
                    $m['month'] = (int) $m['month'];
                }

                $totalAmount = count($monthsToPay) * $data['amount'];

                // 3. Create Monthly Fee Plans
                foreach ($monthsToPay as $m) {
                    MonthlyFeePlan::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'year' => $m['year'],
                            'month' => $m['month'],
                        ],
                        [
                            'payable_amount' => $data['amount'],
                            'reason' => 'Pre-paid Import',
                            'set_by' => 1,
                        ]
                    );
                }

                // 4. Create Payment Record
                $paymentId = DB::table('fee_payments')->insertGetId([
                    'student_id' => $student->id,
                    'paid_amount' => $totalAmount,
                    'payment_date' => Carbon::now()->format('Y-m-d'),
                    'receipt_issued' => 0,
                    'entered_by' => 1,
                    'remarks' => 'Pre-paid bulk import starting Mar 2026',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                // 5. Create Payment Allocations
                foreach ($monthsToPay as $m) {
                    DB::table('fee_payment_allocations')->insert([
                        'fee_payment_id' => $paymentId,
                        'student_id' => $student->id,
                        'year' => $m['year'],
                        'month' => $m['month'],
                        'allocated_amount' => $data['amount'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }

                $this->command->info("✅ Processed {$data['roll_number']} ({$data['name']}) - " . count($monthsToPay) . " months (Total: ₹{$totalAmount})");
            }
            
            DB::commit();
            $this->command->info('');
            $this->command->info('🎉 Pre-paid fee seeding completed successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Fatal Error: " . $e->getMessage());
            throw $e;
        }
    }
}
