<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;

class CleanupImportedStudents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:purge-students';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete ALL users with role=student and their profiles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = User::withTrashed()->where('role', 'student')->count();
        
        if ($count === 0) {
            $this->info("No students found.");
            return;
        }
        
        if (!$this->confirm("Are you sure you want to PERMANENTLY DELETE ALL {$count} students? This includes ALL student accounts and cannot be undone (Force Delete).")) {
            $this->info("Operation cancelled.");
            return;
        }
        
        $this->info("Permanently deleting {$count} students...");
        
        // Chunk processing to handle large datasets
        User::withTrashed()->where('role', 'student')->chunk(100, function ($users) {
            foreach ($users as $user) {
                // Delete associated student profile
                if ($user->student) {
                    $user->student->forceDelete();
                }
                $user->forceDelete();
                $this->output->write('.');
            }
        });
        
        $this->newLine();
        $this->info("Successfully force deleted {$count} students.");
    }
}
