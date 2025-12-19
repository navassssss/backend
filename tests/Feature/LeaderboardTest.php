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

    // Student 1 has high total points (150) but no points this month
    // Student 2 has lower total points (100) but earned 60 this month

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

test('class leaderboard is sorted by total points', function () {
    // Set class points
    $this->class1->update(['total_points' => 250]);
    $this->class2->update(['total_points' => 350]);

    $response = $this->getJson('/api/leaderboard/classes?type=overall');

    $response->assertStatus(200);

    $classes = $response->json();

    // Should be sorted descending
    expect($classes[0]['rank'])->toBe(1);
    expect($classes[0]['class_name'])->toBe('Class 11B');
    expect($classes[0]['points'])->toBe(350);

    expect($classes[1]['rank'])->toBe(2);
    expect($classes[1]['class_name'])->toBe('Class 12A');
    expect($classes[1]['points'])->toBe(250);
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

    // Class 1 should have 80 monthly points (50 + 30)

    PointsLog::create([
        'student_id' => $this->student3->id,
        'class_id' => $this->class2->id,
        'points' => 100,
        'source' => 'achievement',
        'month' => $currentMonth,
        'year' => $currentYear,
    ]);

    // Class 2 should have 100 monthly points

    $response = $this->getJson('/api/leaderboard/classes?type=monthly');

    $response->assertStatus(200);

    $classes = $response->json();

    // Class 2 should be first
    expect($classes[0]['rank'])->toBe(1);
    expect($classes[0]['class_name'])->toBe('Class 11B');
    expect($classes[0]['points'])->toBe(100);

    // Class 1 should be second
    expect($classes[1]['rank'])->toBe(2);
    expect($classes[1]['class_name'])->toBe('Class 12A');
    expect($classes[1]['points'])->toBe(80);
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
