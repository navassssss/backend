<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Student;
use App\Services\FeeManagementService;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $feeService = app(FeeManagementService::class);
        $students = Student::all();

        $count = 0;
        foreach ($students as $student) {
            try {
                // Reallocate clears all payment allocations and perfectly reconstructs them
                // chronologically, filling the oldest unpaid month first.
                $feeService->reallocateStudentPayments($student->id);
                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to reallocate student {$student->id}: " . $e->getMessage());
            }
        }

        echo "Successfully reallocated and fixed chronological gaps for {$count} students.\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reallocation is safely repeatable
    }
};
