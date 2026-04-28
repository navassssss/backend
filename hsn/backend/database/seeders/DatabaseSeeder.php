<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed in order: classes → categories → principal → students (which creates achievements)
        $this->call([
            ClassRoomSeeder::class,
            AchievementCategorySeeder::class,
            PrincipalSeeder::class,
            StudentSeeder::class,
        ]);
        
        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('📊 Login credentials:');
        $this->command->info('   Principal: principal@school.edu / password');
        $this->command->info('   Manager: manager@school.edu / password');
        $this->command->info('   Students: Check database for usernames, all passwords are "password"');
    }
}
