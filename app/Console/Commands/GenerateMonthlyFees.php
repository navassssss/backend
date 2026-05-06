<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Services\FeeManagementService;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:generate-monthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly fee plans for all active students up to the current month';

    /**
     * Execute the console command.
     */
    public function handle(FeeManagementService $feeService)
    {
        $this->info('Starting monthly fee generation...');
        $students = Student::all();
        $count = 0;

        foreach ($students as $student) {
            try {
                // This safely creates missing plans up to the current month
                $feeService->ensureFeePlans($student->id);
                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to generate fees for student {$student->id}: " . $e->getMessage());
                $this->error("Error on student {$student->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully processed fee generation for {$count} students.");
        return Command::SUCCESS;
    }
}
