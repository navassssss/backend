<?php

namespace App\Console\Commands;

use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\Achievement;
use App\Models\PointsLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculatePoints extends Command
{
    protected $signature = 'points:recalculate';
    protected $description = 'Recalculate total points for students and classes, and rebuild points logs based on approved achievements.';

    public function handle()
    {
        $this->info('Starting points recalculation...');

        DB::transaction(function () {
            // 1. Rebuild points_logs table for achievements
            // Clear existing points logs of source = 'achievement'
            PointsLog::where('source', 'achievement')->delete();
            $this->info('Cleared existing achievement points logs.');

            // Get all approved achievements
            $achievements = Achievement::with('student')->where('status', 'approved')->get();
            $this->info('Found ' . $achievements->count() . ' approved achievements.');

            foreach ($achievements as $achievement) {
                if (!$achievement->student) {
                    continue;
                }

                PointsLog::create([
                    'student_id' => $achievement->student_id,
                    'class_id' => $achievement->student->class_id,
                    'achievement_id' => $achievement->id,
                    'points' => $achievement->points,
                    'source' => 'achievement',
                    'month' => $achievement->created_at->month,
                    'year' => $achievement->created_at->year,
                    'created_at' => $achievement->created_at,
                    'updated_at' => $achievement->updated_at,
                ]);
            }
            $this->info('Rebuilt achievement points logs.');

            // 2. Recalculate student total_points
            $students = Student::all();
            foreach ($students as $student) {
                $totalPoints = Achievement::where('student_id', $student->id)
                    ->where('status', 'approved')
                    ->sum('points');

                $student->total_points = $totalPoints;
                $student->save();
            }
            $this->info('Recalculated total points for ' . $students->count() . ' students.');

            // 3. Recalculate class_room total_points
            $classes = ClassRoom::all();
            foreach ($classes as $class) {
                $classPoints = Achievement::whereHas('student', function ($q) use ($class) {
                    $q->where('class_id', $class->id);
                })
                ->whereHas('category', function ($q) {
                    $q->where('applies_to_class', true);
                })
                ->where('status', 'approved')
                ->sum('points');

                $class->total_points = $classPoints;
                $class->save();
            }
            $this->info('Recalculated total points for ' . $classes->count() . ' classes.');
        });

        $this->info('Points recalculation completed successfully!');
        return 0;
    }
}
