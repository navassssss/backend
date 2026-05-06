<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\FeeManagementService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $feeService = app(FeeManagementService::class);
        $studentId = 788;
        
        try {
            $feeService->reallocateStudentPayments($studentId);
            echo "Successfully reallocated payments for student {$studentId}.\n";
        } catch (\Exception $e) {
            echo "Failed to reallocate payments for student {$studentId}: " . $e->getMessage() . "\n";
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
