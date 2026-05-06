<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Student;
use App\Services\FeeManagementService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the student by roll_number or username, or name matching Jusaid
        $student = Student::where('username', '788')
            ->orWhere('roll_number', '788')
            ->orWhereHas('user', function($q) {
                $q->where('name', 'like', '%Sayyid Jusaid%');
            })
            ->first();

        if ($student) {
            $feeService = app(FeeManagementService::class);
            try {
                $feeService->reallocateStudentPayments($student->id);
                echo "Successfully reallocated payments for student {$student->id} (Roll: 788).\n";
            } catch (\Exception $e) {
                echo "Failed to reallocate payments: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Student 788 (Sayyid Jusaid) not found in the database.\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reallocation is safely repeatable, no down migration needed.
    }
};
