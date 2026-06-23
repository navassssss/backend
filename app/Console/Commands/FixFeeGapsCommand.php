<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Services\FeeManagementService;
use Illuminate\Support\Facades\DB;

class FixFeeGapsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fee:fix-gaps {--dry-run : Only show students with gaps without modifying data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finds and fixes students who have payments allocated to future months while current/past months remain unpaid.';

    private FeeManagementService $feeService;

    public function __construct(FeeManagementService $feeService)
    {
        parent::__construct();
        $this->feeService = $feeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info("Scanning for students with fixed monthly fee > 0 who have chronological fee gaps...");

        // Get students with fixed amount > 0
        $students = Student::where('monthly_fee', '>', 0)
            ->with(['user', 'class'])
            ->get();

        $studentsWithGaps = [];

        $bar = $this->output->createProgressBar(count($students));

        foreach ($students as $student) {
            $status = $this->feeService->getStudentMonthlyStatus($student->id);
            
            $foundUnpaid = false;
            $hasGap = false;

            foreach ($status as $monthStatus) {
                $balance = $monthStatus['balance'];
                $paid = $monthStatus['paid'];

                if ($balance > 0) {
                    // We found a month that is not fully paid
                    $foundUnpaid = true;
                } elseif ($paid > 0 && $foundUnpaid) {
                    // We found a month that HAS payments, but an earlier month was NOT fully paid!
                    $hasGap = true;
                    break;
                }
            }

            if ($hasGap) {
                $studentsWithGaps[] = $student;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (empty($studentsWithGaps)) {
            $this->info("✅ No students with fee gaps found! Everyone's allocations are chronologically correct.");
            return 0;
        }

        $this->warn("⚠️ Found " . count($studentsWithGaps) . " student(s) with fee gaps (future months paid while past/current are unpaid):");

        $tableData = [];
        foreach ($studentsWithGaps as $student) {
            $tableData[] = [
                'ID' => $student->id,
                'Name' => $student->user->name ?? 'Unknown',
                'Class' => $student->class->name ?? 'Unknown',
                'Monthly Fee' => $student->monthly_fee,
            ];
        }

        $this->table(['ID', 'Name', 'Class', 'Monthly Fee'], $tableData);

        if ($isDryRun) {
            $this->info("ℹ️ Dry-run complete. Run without --dry-run to fix these allocations.");
            return 0;
        }

        $this->info("Fixing allocations for " . count($studentsWithGaps) . " students...");

        DB::beginTransaction();
        try {
            $fixBar = $this->output->createProgressBar(count($studentsWithGaps));
            
            foreach ($studentsWithGaps as $student) {
                // Reallocate all payments sequentially to eliminate gaps
                $this->feeService->reallocateStudentPayments($student->id);
                $fixBar->advance();
            }
            
            DB::commit();
            $fixBar->finish();
            $this->newLine(2);
            $this->info("✅ Successfully fixed chronological gaps for all affected students!");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine(2);
            $this->error("❌ Error occurred while fixing allocations: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
