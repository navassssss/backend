<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use App\Models\Student;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only target academic (is_hifz = false) students who have a valid roll_number
        $students = Student::where('is_hifz', false)
            ->whereNotNull('roll_number')
            ->where('roll_number', '!=', '')
            ->with('user')
            ->get();

        foreach ($students as $student) {
            $rollNumber = trim($student->roll_number);

            // Update student record's username
            $student->update([
                'username' => $rollNumber
            ]);

            // Update associated user's username & password
            if ($student->user) {
                $student->user->update([
                    'username' => $rollNumber,
                    'password' => Hash::make($rollNumber . '@dhic')
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed for credential reset
    }
};
