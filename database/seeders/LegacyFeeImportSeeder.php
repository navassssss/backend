<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Models\MonthlyFeePlan;

/**
 * Imports legacy fee balances from a CSV file and distributes the
 * remaining balance into individual MonthlyFeePlan records.
 *
 * CSV columns (0-indexed):
 *   0 → AD_NO & Name  (e.g. "791 MUHAMMED FALAH" or "H220 JOHN")
 *   1 → Class
 *   2 → Monthly Fee
 *   3 → Total Remaining Balance
 *
 * Balance distribution rule:
 *   - unpaidMonths = floor(remaining / monthlyFee)
 *   - partialAmount = remaining % monthlyFee  (creates 1 extra older plan if > 0)
 *   - Plans generated starting April 2026 going backwards.
 */
class LegacyFeeImportSeeder extends Seeder
{
    /** Anchor: the current month from which backwards generation starts. */
    private const ANCHOR_YEAR  = 2026;
    private const ANCHOR_MONTH = 4;

    public function run(): void
    {
        $filePath = database_path('seeders/data/monthly.csv');

        if (! file_exists($filePath)) {
            $this->command->error("CSV file not found at: {$filePath}");
            return;
        }

        $this->command->info("Reading CSV from: {$filePath}");

        $file = fopen($filePath, 'r');
        fgetcsv($file); // skip header

        DB::beginTransaction();

        try {
            $processed   = 0;
            $skipped     = 0;
            $zeroBalance = []; // students found but with no remaining balance

            while (($row = fgetcsv($file)) !== false) {
                $adNoName     = $row[0] ?? '';
                $monthlyStr   = $row[2] ?? 0;
                $remainingStr = $row[3] ?? 0;

                // ── Extract roll number ────────────────────────────────────
                if (! preg_match('/^([Hh]?\d+)/', trim($adNoName), $matches)) {
                    continue;
                }
                $rollNo = strtoupper($matches[1]);

                // ── Parse amounts ──────────────────────────────────────────
                $monthlyFee     = $this->parseAmount($monthlyStr);
                $totalRemaining = $this->parseAmount($remainingStr);

                // ── Student lookup (always first, so we can show names) ────
                $student = Student::where('roll_number', $rollNo)->first();

                if (! $student) {
                    $this->command->warn("  [{$rollNo}] Not found in DB. Skipping.");
                    $skipped++;
                    continue;
                }

                $label = "[{$rollNo}] {$student->user->name}";

                // ── Detect Hifz student via H-prefix roll number ───────────
                $isHifz = str_starts_with($rollNo, 'H');

                // ── Invalid fee data guard ─────────────────────────────────
                if ($monthlyFee <= 0 || $totalRemaining < 0) {
                    $this->command->warn("  {$label} — invalid data (monthly={$monthlyFee}, remaining={$totalRemaining}). Skipping.");
                    $skipped++;
                    continue;
                }

                // ── Zero remaining — fully paid / no debt ──────────────────
                if ($totalRemaining === 0.0) {
                    $zeroBalance[] = $label;
                    // Still update monthly_fee and is_hifz if applicable
                    if ($monthlyFee > 0) {
                        $student->monthly_fee = $monthlyFee;
                        $student->is_hifz = $isHifz;
                        $student->save();
                    }
                    continue;
                }

                // ── Normal processing ──────────────────────────────────────
                $this->command->info("  Processing {$label} | monthly={$monthlyFee}  remaining={$totalRemaining}");

                $student->monthly_fee = $monthlyFee;
                $student->is_hifz = $isHifz;
                $student->save();

                $plans = $this->buildFeePlans($student->id, $monthlyFee, $totalRemaining);

                foreach ($plans as $plan) {
                    MonthlyFeePlan::updateOrCreate(
                        [
                            'student_id' => $plan['student_id'],
                            'year'       => $plan['year'],
                            'month'      => $plan['month'],
                        ],
                        [
                            'payable_amount' => $plan['payable_amount'],
                            'reason'         => $plan['reason'],
                        ]
                    );
                }

                $this->command->line("    → " . count($plans) . " fee plan(s) created/updated.");
                $processed++;
            }

            fclose($file);
            DB::commit();

            // ── Final summary ──────────────────────────────────────────────
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════════════');
            $this->command->info('  Legacy Import Completed');
            $this->command->info("  Plans generated (processed)  : {$processed}");
            $this->command->info("  Skipped (not found/invalid)  : {$skipped}");
            $this->command->info("  Zero balance (no plans made) : " . count($zeroBalance));
            $this->command->info('═══════════════════════════════════════════════');

            if (! empty($zeroBalance)) {
                $this->command->info('');
                $this->command->warn('  Zero-balance students (paid up / no debt — no fee plans generated):');
                foreach ($zeroBalance as $entry) {
                    $this->command->line("    - {$entry}");
                }
                $this->command->info('');
            }

        } catch (\Throwable $e) {
            fclose($file);
            DB::rollBack();
            $this->command->error("Fatal error: " . $e->getMessage());
            throw $e;
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Build the list of MonthlyFeePlan payloads for a student.
     *
     * Generates plans starting from ANCHOR (Apr 2026) going backwards:
     *   - floor(remaining / monthly) full-amount plans
     *   - 1 partial plan for the remainder (if any), as the oldest month
     *
     * @return array<int, array{student_id: int, year: int, month: int, payable_amount: float, reason: string}>
     */
    private function buildFeePlans(int $studentId, float $monthlyFee, float $totalRemaining): array
    {
        $unpaidMonths  = (int) floor($totalRemaining / $monthlyFee);
        $partialAmount = round(fmod($totalRemaining, $monthlyFee), 2);

        $plans  = [];
        $cursor = Carbon::create(self::ANCHOR_YEAR, self::ANCHOR_MONTH, 1);

        // Full monthly plans — newest first (Apr 2026 → backwards)
        for ($i = 0; $i < $unpaidMonths; $i++) {
            $plans[] = $this->makePlanEntry($studentId, $cursor, $monthlyFee);
            $cursor->subMonth();
        }

        // Partial plan — one additional older month
        if ($partialAmount > 0) {
            $plans[] = $this->makePlanEntry($studentId, $cursor, $partialAmount, 'Partial Legacy Balance');
        }

        return $plans;
    }

    /**
     * Assemble a single plan array from a Carbon date.
     */
    private function makePlanEntry(
        int    $studentId,
        Carbon $date,
        float  $amount,
        string $reason = 'Standard Fee (Legacy Import)'
    ): array {
        return [
            'student_id'     => $studentId,
            'year'           => (int) $date->year,
            'month'          => (int) $date->month,
            'payable_amount' => $amount,
            'reason'         => $reason,
        ];
    }

    /**
     * Parse a raw CSV cell into a float.
     * Returns 0 for empty, "NONE", or non-numeric strings.
     */
    private function parseAmount(mixed $value): float
    {
        if (empty($value)) {
            return 0.0;
        }

        $trimmed = strtolower(trim((string) $value));

        if ($trimmed === 'none') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.]/', '', $trimmed);

        return empty($clean) ? 0.0 : (float) $clean;
    }
}
