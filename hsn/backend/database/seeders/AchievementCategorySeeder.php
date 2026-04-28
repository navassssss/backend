<?php

namespace Database\Seeders;

use App\Models\AchievementCategory;
use Illuminate\Database\Seeder;

class AchievementCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Academic Achievements
            [
                'name' => 'Academic Excellence',
                'description' => 'Scored 90% or above in exams',
                'points' => 50,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Perfect Attendance',
                'description' => '100% attendance for the month',
                'points' => 20,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            [
                'name' => 'First Rank',
                'description' => 'Secured first position in class',
                'points' => 100,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Subject Topper',
                'description' => 'Highest marks in a particular subject',
                'points' => 30,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            
            // Sports Achievements
            [
                'name' => 'Sports Winner',
                'description' => 'Won first place in sports competition',
                'points' => 60,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Sports Participation',
                'description' => 'Participated in inter-school sports',
                'points' => 25,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            
            // Cultural & Extra-curricular
            [
                'name' => 'Cultural Event Winner',
                'description' => 'Won in cultural competition',
                'points' => 40,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Art & Craft Excellence',
                'description' => 'Outstanding performance in art activities',
                'points' => 30,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            
            // Individual Achievements (doesn't add to class)
            [
                'name' => 'Community Service',
                'description' => 'Volunteered for community work',
                'points' => 35,
                'applies_to_class' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Personal Development',
                'description' => 'Completed personal skill development course',
                'points' => 25,
                'applies_to_class' => false,
                'is_active' => true,
            ],
            
            // Behavior & Discipline
            [
                'name' => 'Class Monitor',
                'description' => 'Served as class monitor',
                'points' => 15,
                'applies_to_class' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Discipline Award',
                'description' => 'Exemplary discipline throughout month',
                'points' => 20,
                'applies_to_class' => true,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            AchievementCategory::create($category);
        }
    }
}
