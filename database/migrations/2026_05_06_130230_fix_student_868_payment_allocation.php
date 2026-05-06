<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Student;
use App\Models\FeePaymentAllocation;
use App\Models\MonthlyFeePlan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $student = Student::where('roll_number', '868')->first();
        if ($student) {
            // Find June allocation
            $juneAllocation = FeePaymentAllocation::where('student_id', $student->id)
                ->where('year', 2026)
                ->where('month', 6)
                ->first();

            if ($juneAllocation) {
                // Check if May allocation exists
                $hasMayAllocation = FeePaymentAllocation::where('student_id', $student->id)
                    ->where('year', 2026)
                    ->where('month', 5)
                    ->exists();

                if (!$hasMayAllocation) {
                    // Move the allocation back to May
                    $juneAllocation->month = 5;
                    $juneAllocation->save();

                    // Remove the June fee plan
                    MonthlyFeePlan::where('student_id', $student->id)
                        ->where('year', 2026)
                        ->where('month', 6)
                        ->delete();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed for data fix
    }
};
