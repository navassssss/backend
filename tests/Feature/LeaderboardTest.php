<?php

use App\Models\Achievement;
use App\Models\AchievementCategory;
use App\Models\ClassRoom;
use App\Models\PointsLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create departments
    $this->dept1 = \App\Models\Department::create(['name' => 'Civilizational Studies']);
    $this->dept2 = \App\Models\Department::create(['name' => 'Hadith & Related Sciences']);

    // Create classes
    $this->class1 = ClassRoom::create([
        'name' => 'Class 12A',
        'department' => 'Science',
        'total_points' => 0,
    ]);

    $this->class2 = ClassRoom::create([
        'name' => 'Class 11B',
        'department' => 'Commerce',
        'total_points' => 0,
    ]);

    // Create students
    $user1 = User::create([
        'name' => 'Student One',
        'email' => 'student1@test.com',
        'password' => Hash::make('password'),
        'role' => 'student',
    ]);

    $this->student1 = Student::create([
        'user_id' => $user1->id,
        'class_id' => $this->class1->id,
        'department_id' => $this->dept1->id,
        'username' => 'student1',
        'roll_number' => 'S001',
        'joined_at' => now(),
        'total_points' => 150,
    ]);

    $user2 = User::create([
        'name' => 'Student Two',
        'email' => 'student2@test.com',
        'password' => Hash::make('password'),
        'role' => 'student',
    ]);

    $this->student2 = Student::create([
        'user_id' => $user2->id,
        'class_id' => $this->class1->id,
        'department_id' => $this->dept1->id,
        'username' => 'student2',
        'roll_number' => 'S002',
        'joined_at' => now(),
        'total_points' => 100,
    ]);

    $user3 = User::create([
        'name' => 'Student Three',
        'email' => 'student3@test.com',
        'password' => Hash::make('password'),
        'role' => 'student',
    ]);

    $this->student3 = Student::create([
        'user_id' => $user3->id,
        'class_id' => $this->class2->id,
        'department_id' => $this->dept2->id,
        'username' => 'student3',
        'roll_number' => 'S003',
        'joined_at' => now(),
        'total_points' => 200,
    ]);
});

test('overall student leaderboard is sorted by total points', function () {
    $response = $this->getJson('/api/leaderboard/students?type=overall');

    $response->assertStatus(200);

    $students = $response->json();

    // Should be sorted by total_points descending
    expect($students[0]['rank'])->toBe(1);
    expect($students[0]['name'])->toBe('Student Three');
    expect($students[0]['points'])->toBe(200);

    expect($students[1]['rank'])->toBe(2);
    expect($students[1]['name'])->toBe('Student One');
    expect($students[1]['points'])->toBe(150);

    expect($students[2]['rank'])->toBe(3);
    expect($students[2]['name'])->toBe('Student Two');
    expect($students[2]['points'])->toBe(100);
});

test('monthly leaderboard calculates from points logs', function () {
    $currentMonth = now()->month;
    $currentYear = now()->year;

    // Create points logs for current month
    PointsLog::create([
        'student_id' => $this->student1->id,
        'class_id' => $this->class1->id,
        'points' => 50,
        'source' => 'achievement',
        'month' => $currentMonth,
        'year' => $currentYear,
    ]);

    PointsLog::create([
        'student_id' => $this->student2->id,
        'class_id' => $this->class1->id,
        'points' => 80,
        'source' => 'achievement',
        'month' => $currentMonth,
        'year' => $currentYear,
    ]);

    // Create points log for previous month (should not count)
    PointsLog::create([
        'student_id' => $this->student1->id,
        'class_id' => $this->class1->id,
        'points' => 100,
        'source' => 'achievement',
        'month' => $currentMonth - 1 ?: 12,
        'year' => $currentMonth - 1 > 0 ? $currentYear : $currentYear - 1,
    ]);

    $response = $this->getJson('/api/leaderboard/students?type=monthly');

    $response->assertStatus(200);

    $students = $response->json();

    // Student 2 should be first with 80 monthly points
    expect($students[0]['name'])->toBe('Student Two');
    expect($students[0]['points'])->toBe(80);

    // Student 1 should be second with 50 monthly points (not 150 total)
    expect($students[1]['name'])->toBe('Student One');
    expect($students[1]['points'])->toBe(50);
});

test('monthly leaderboard resets correctly by only counting current month', function () {
    $currentMonth = now()->month;
    $currentYear = now()->year;

    PointsLog::create([
        'student_id' => $this->student2->id,
        'class_id' => $this->class1->id,
        'points' => 60,
        'source' => 'achievement',
        'month' => $currentMonth,
        'year' => $currentYear,
    ]);

    $response = $this->getJson('/api/leaderboard/students?type=monthly');

    $response->assertStatus(200);

    $students = $response->json();

    // Student 2 should be ranked higher in monthly leaderboard
    $topStudent = collect($students)->firstWhere('name', 'Student Two');
    expect($topStudent['points'])->toBe(60);

    // Student 1 should have 0 monthly points
    $studentOne = collect($students)->firstWhere('name', 'Student One');
    expect($studentOne['points'])->toBe(0);
});

test('class leaderboard is sorted by average points', function () {
    // Class 1 (Class 12A) has 2 students. Set total points to 250 -> average is 125
    $this->class1->update(['total_points' => 250]);
    // Class 2 (Class 11B) has 1 student. Set total points to 200 -> average is 200
    $this->class2->update(['total_points' => 200]);

    $response = $this->getJson('/api/leaderboard/classes?type=overall');

    $response->assertStatus(200);

    $classes = $response->json();

    // Class 2 should be ranked 1st since its average is 200 (vs 125 for Class 1)
    expect($classes[0]['rank'])->toBe(1);
    expect($classes[0]['class_name'])->toBe('Class 11B');
    expect($classes[0]['average'])->toEqual(200);

    expect($classes[1]['rank'])->toBe(2);
    expect($classes[1]['class_name'])->toBe('Class 12A');
    expect($classes[1]['average'])->toEqual(125);
});

test('monthly class leaderboard calculates from points logs', function () {
    $currentMonth = now()->month;
    $currentYear = now()->year;

    // Create points logs for current month
    PointsLog::create([
        'student_id' => $this->student1->id,
        'class_id' => $this->class1->id,
        'points' => 50,
        'source' => 'achievement',
        'month' => $currentMonth,
        'year' => $currentYear,
    ]);

    PointsLog::create([
        'student_id' => $this->student2->id,
        'class_id' => $this->class1->id,
        'points' => 30,
        'source' => 'achievement',
        'month' => $currentMonth,
        'year' => $currentYear,
    ]);

    // Class 1 total monthly = 80 points. Has 2 students -> average = 40

    PointsLog::create([
        'student_id' => $this->student3->id,
        'class_id' => $this->class2->id,
        'points' => 100,
        'source' => 'achievement',
        'month' => $currentMonth,
        'year' => $currentYear,
    ]);

    // Class 2 total monthly = 100 points. Has 1 student -> average = 100

    $response = $this->getJson('/api/leaderboard/classes?type=monthly');

    $response->assertStatus(200);

    $classes = $response->json();

    // Class 2 should be first based on average (100 vs 40)
    expect($classes[0]['rank'])->toBe(1);
    expect($classes[0]['class_name'])->toBe('Class 11B');
    expect($classes[0]['average'])->toEqual(100);

    // Class 1 should be second
    expect($classes[1]['rank'])->toBe(2);
    expect($classes[1]['class_name'])->toBe('Class 12A');
    expect($classes[1]['average'])->toEqual(40);
});

test('department leaderboard calculates and ranks by total student points', function () {
    // Dept 1 has Student 1 (150 pts) and Student 2 (100 pts) -> 250 total
    // Dept 2 has Student 3 (200 pts) -> 200 total

    $response = $this->getJson('/api/leaderboard/departments?type=overall');

    $response->assertStatus(200);

    $departments = $response->json();

    // Dept 1 should be first (250 vs 200)
    expect($departments[0]['rank'])->toBe(1);
    expect($departments[0]['department_name'])->toBe('Civilizational Studies');
    expect($departments[0]['points'])->toBe(250);

    // Dept 2 should be second
    expect($departments[1]['rank'])->toBe(2);
    expect($departments[1]['department_name'])->toBe('Hadith & Related Sciences');
    expect($departments[1]['points'])->toBe(200);
});

test('student computed stars property works correctly', function () {
    // 150 points / 20 = 7 stars (floor)
    expect($this->student1->stars)->toBe(7);

    // 100 points / 20 = 5 stars
    expect($this->student2->stars)->toBe(5);

    // 200 points / 20 = 10 stars
    expect($this->student3->stars)->toBe(10);

    // Update points and verify stars recalculate
    $this->student1->update(['total_points' => 39]);
    expect($this->student1->fresh()->stars)->toBe(1); // 39 / 20 = 1

    $this->student1->update(['total_points' => 19]);
    expect($this->student1->fresh()->stars)->toBe(0); // 19 / 20 = 0
});
