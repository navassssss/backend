<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Imports Hifz students from database/seeders/data/hifz_students.csv
 *
 * CSV format (with header row):
 *   AD_NO,NAME
 *   H228,MUHAMMED HASAN PS
 *
 * - AD_NO must start with H (e.g. H228)
 * - All students are assigned to the single "Hifz" class (is_hifz = true)
 * - Student roll_number = username = the H-prefixed AD_NO (e.g. "H228")
 * - "H228" and "228" are separate students — no collision
 * - Safe to re-run (idempotent via updateOrCreate)
 */
class HifzStudentSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('seeders/data/hifz_students.csv');

        if (! file_exists($filePath)) {
            $this->command->error("CSV not found: {$filePath}");
            return;
        }

        DB::beginTransaction();

        try {
            // ── Ensure Hifz class exists ───────────────────────────────────
            $hifzClass = ClassRoom::firstOrCreate(
                ['name' => 'Hifz'],
                [
                    'department'  => 'hifz',
                    'total_points' => 0,
                    'is_hifz'     => true,
                ]
            );

            // Ensure is_hifz is set even if class already existed
            if (! $hifzClass->is_hifz) {
                $hifzClass->is_hifz = true;
                $hifzClass->save();
            }

            $this->command->info("Hifz class ID: {$hifzClass->id}");

            // ── Parse CSV ──────────────────────────────────────────────────
            $file = fopen($filePath, 'r');
            fgetcsv($file); // skip header

            $created = 0;
            $updated = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (count($row) < 2) continue;

                $adNo = strtoupper(trim($row[0]));
                $name = trim($row[1]);

                if (empty($adNo) || empty($name)) continue;

                // Enforce H-prefix
                if (! str_starts_with($adNo, 'H')) {
                    $this->command->warn("  Skipping [{$adNo}] — no H prefix.");
                    continue;
                }

                $email = strtolower($adNo) . '@hasanath.com'; // e.g. h228@hasanath.com

                // ── User ──────────────────────────────────────────────────
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name'       => $name,
                        'password'   => Hash::make($adNo . '@portal'),
                        'role'       => 'student',
                        'department' => 'hifz',
                    ]
                );

                // Keep name up-to-date
                if ($user->name !== $name) {
                    $user->name = $name;
                    $user->save();
                }

                // ── Student ───────────────────────────────────────────────
                $exists = Student::where('user_id', $user->id)->exists();

                Student::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'class_id'     => $hifzClass->id,
                        'username'     => $adNo,
                        'roll_number'  => $adNo,   // "H228" — distinct from "228"
                        'joined_at'    => now(),
                        'total_points' => 0,
                        'wallet_balance' => 0,
                        'is_hifz'      => true,
                    ]
                );

                $exists ? $updated++ : $created++;
                $this->command->line("  [{$adNo}] {$name} — " . ($exists ? 'updated' : 'created'));
            }

            fclose($file);
            DB::commit();

            $this->command->info('');
            $this->command->info('═══════════════════════════════════════════════');
            $this->command->info('  Hifz Import Complete');
            $this->command->info("  Created : {$created}");
            $this->command->info("  Updated : {$updated}");
            $this->command->info('═══════════════════════════════════════════════');

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('Fatal: ' . $e->getMessage());
            throw $e;
        }
    }
}
