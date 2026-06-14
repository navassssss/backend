<?php

use App\Models\ClassRoom;
use App\Models\Department;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('departments endpoint lists all departments', function () {
    $dept1 = Department::create(['name' => 'Department A']);
    $dept2 = Department::create(['name' => 'Department B']);

    $user = User::create([
        'name' => 'Principal',
        'email' => 'principal@test.com',
        'password' => bcrypt('password'),
        'role' => 'principal',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/departments');

    $response->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJsonFragment(['name' => 'Department A'])
        ->assertJsonFragment(['name' => 'Department B']);
});

test('subject effective student ids returns only students belonging to the department', function () {
    $dept1 = Department::create(['name' => 'Department A']);
    $dept2 = Department::create(['name' => 'Department B']);

    $class = ClassRoom::create(['name' => 'Class 1A']);

    $user1 = User::create(['name' => 'Student 1', 'email' => 's1@test.com', 'password' => bcrypt('password'), 'role' => 'student']);
    $student1 = Student::create([
        'user_id' => $user1->id,
        'class_id' => $class->id,
        'department_id' => $dept1->id,
        'username' => 's1',
    ]);

    $user2 = User::create(['name' => 'Student 2', 'email' => 's2@test.com', 'password' => bcrypt('password'), 'role' => 'student']);
    $student2 = Student::create([
        'user_id' => $user2->id,
        'class_id' => $class->id,
        'department_id' => $dept2->id,
        'username' => 's2',
    ]);

    $teacher = User::create(['name' => 'Teacher', 'email' => 't@test.com', 'password' => bcrypt('password'), 'role' => 'teacher']);
    
    $subject = Subject::create([
        'name' => 'Subject A',
        'code' => 'SUB-A',
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'final_max_marks' => 30,
        'assignment_scope' => 'department',
        'department_id' => $dept1->id,
    ]);

    $effectiveStudentIds = $subject->effectiveStudentIds();

    expect($effectiveStudentIds->toArray())->toBe([$student1->id]);
});
