<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanStudentsSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        $this->command->info('Cleaning up Student data...');

        // 1. Delete all Student profiles (including dependent fee data if cascades are set, otherwise this might fail or leave orphans)
        // Truncate is faster and resets IDs
        DB::table('students')->truncate();
        
        // 2. Delete all Users who are students
        $count = DB::table('users')->where('role', 'student')->delete();

        Schema::enableForeignKeyConstraints();

        $this->command->info("Completed. Deleted {$count} student user accounts and all student profiles.");
    }
}
