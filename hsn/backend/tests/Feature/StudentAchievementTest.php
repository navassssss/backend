<?php

use App\Events\AchievementApproved;
use App\Models\Achievement;
use App\Models\AchievementCategory;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create principal
    $this->principalUser = User::create([
        'name' => 'Principal User',
        'email' => 'principal@test.com',
        'password' => Hash::make('password'),
        'role' => 'principal',
    ]);

    // Create class
    $this->class = ClassRoom::create([
        'name' => 'Class 12A',
        'department' => 'Science',
        'total_points' => 0,
    ]);

    // Create student user
    $this->studentUser = User::create([
        'name' => 'Test Student',
        'email' => 'student@test.com',
        'password' => Hash::make('password'),
        'role' => 'student',
    ]);

    // Create student profile
    $this->student = Student::create([
        'user_id' => $this->studentUser->id,
        'class_id' => $this->class->id,
        'username' => 'teststudent',
        'roll_number' => 'TEST-001',
        'joined_at' => now(),
        'total_points' => 0,
    ]);

    // Create achievement category
    $this->category = AchievementCategory::create([
        'name' => 'Academic Excellence',
        'description' => 'Top grades',
        'points' => 50,
        'applies_to_class' => true,
        'is_active' => true,
    ]);
});

test('student can submit achievement with pending status', function () {
    $response = $this->actingAs($this->studentUser, 'sanctum')
        ->postJson('/api/student/achievements', [
            'achievement_category_id' => $this->category->id,
            'title' => 'First Place in Math Olympiad',
            'description' => 'Won gold medal',
        ]);

    $response->assertStatus(201);

    // Check achievement was created with pending status
    $achievement = Achievement::first();
    expect($achievement->status)->toBe('pending');
    expect($achievement->student_id)->toBe($this->student->id);
    expect($achievement->points)->toBe(50); // Snapshot from category
    expect($achievement->approved_by)->toBeNull();
    expect($achievement->approved_at)->toBeNull();

    // Points should NOT be added yet
    expect($this->student->fresh()->total_points)->toBe(0);
    expect($this->class->fresh()->total_points)->toBe(0);
});

test('principal approval adds points to student and fires event', function () {
    Event::fake([AchievementApproved::class]);

    // Create pending achievement
    $achievement = Achievement::create([
        'student_id' => $this->student->id,
        'achievement_category_id' => $this->category->id,
        'title' => 'Test Achievement',
        'description' => 'Test',
        'points' => 50,
        'status' => 'pending',
    ]);

    // Principal approves
    $response = $this->actingAs($this->principalUser, 'sanctum')
        ->postJson("/api/achievements/{$achievement->id}/approve", [
            'review_note' => 'Well done!',
        ]);

    $response->assertStatus(200);

    // Check achievement status updated
    $achievement->refresh();
    expect($achievement->status)->toBe('approved');
    expect($achievement->approved_by)->toBe($this->principalUser->id);
    expect($achievement->approved_at)->not->toBeNull();
    expect($achievement->review_note)->toBe('Well done!');

    // Verify event was fired
    Event::assertDispatched(AchievementApproved::class, function ($event) use ($achievement) {
        return $event->achievement->id === $achievement->id;
    });

    // NOTE: Points will NOT be added because Event::fake() prevents listeners from running
    // expect($this->student->fresh()->total_points)->toBe(50);
});

test('class points increase when applies_to_class is true', function () {
    // Category with applies_to_class = true
    $achievement = Achievement::create([
        'student_id' => $this->student->id,
        'achievement_category_id' => $this->category->id, // applies_to_class = true
        'title' => 'Team Achievement',
        'points' => 50,
        'status' => 'pending',
    ]);

    $initialClassPoints = $this->class->total_points;

    // Approve achievement
    $this->actingAs($this->principalUser, 'sanctum')
        ->postJson("/api/achievements/{$achievement->id}/approve");

    // Both student and class should get points
    expect($this->student->fresh()->total_points)->toBe(50);
    expect($this->class->fresh()->total_points)->toBe($initialClassPoints + 50);
});

test('class points do not increase when applies_to_class is false', function () {
    // Create category with applies_to_class = false
    $individualCategory = AchievementCategory::create([
        'name' => 'Individual Award',
        'points' => 30,
        'applies_to_class' => false,
        'is_active' => true,
    ]);

    $achievement = Achievement::create([
        'student_id' => $this->student->id,
        'achievement_category_id' => $individualCategory->id,
        'title' => 'Personal Achievement',
        'points' => 30,
        'status' => 'pending',
    ]);

    $initialClassPoints = $this->class->total_points;

    // Approve achievement
    $this->actingAs($this->principalUser, 'sanctum')
        ->postJson("/api/achievements/{$achievement->id}/approve");

    // Only student gets points, NOT class
    expect($this->student->fresh()->total_points)->toBe(30);
    expect($this->class->fresh()->total_points)->toBe($initialClassPoints); // No change
});

test('rejected achievement does not affect points', function () {
    $achievement = Achievement::create([
        'student_id' => $this->student->id,
        'achievement_category_id' => $this->category->id,
        'title' => 'Invalid Achievement',
        'points' => 50,
        'status' => 'pending',
    ]);

    $initialStudentPoints = $this->student->total_points;
    $initialClassPoints = $this->class->total_points;

    // Principal rejects
    $response = $this->actingAs($this->principalUser, 'sanctum')
        ->postJson("/api/achievements/{$achievement->id}/reject", [
            'review_note' => 'Insufficient evidence provided',
        ]);

    $response->assertStatus(200);

    // Check status is rejected
    $achievement->refresh();
    expect($achievement->status)->toBe('rejected');
    expect($achievement->review_note)->toBe('Insufficient evidence provided');

    // Points should NOT change
    expect($this->student->fresh()->total_points)->toBe($initialStudentPoints);
    expect($this->class->fresh()->total_points)->toBe($initialClassPoints);
});

test('updating already approved achievement does not double count points', function () {
    // Create and approve achievement
    $achievement = Achievement::create([
        'student_id' => $this->student->id,
        'achievement_category_id' => $this->category->id,
        'title' => 'First Achievement',
        'points' => 50,
        'status' => 'pending',
    ]);

    // First approval
    $this->actingAs($this->principalUser, 'sanctum')
        ->postJson("/api/achievements/{$achievement->id}/approve");

    $pointsAfterFirstApproval = $this->student->fresh()->total_points;
    expect($pointsAfterFirstApproval)->toBe(50);

    // Try to "approve" again (should not fire event since status doesn't change)
    $achievement->refresh();
    $achievement->update(['status' => 'approved']); // Manual update, no event

    expect($this->student->fresh()->total_points)->toBe(50);
    expect($this->class->fresh()->total_points)->toBe(50);
});

test('student can upload attachments with achievement', function () {
    Storage::fake('public');
    
    $file = Illuminate\Http\UploadedFile::fake()->create('proof.pdf', 100);
    
    $response = $this->actingAs($this->studentUser, 'sanctum')
        ->postJson('/api/student/achievements', [
            'achievement_category_id' => $this->category->id,
            'title' => 'Achievement with Proof',
            'attachments' => [$file],
        ]);
        
    $response->assertStatus(201);
    
    $achievement = Achievement::where('title', 'Achievement with Proof')->first();
    
    // Check attachment record created
    expect($achievement->attachments()->count())->toBe(1);
    $attachment = $achievement->attachments()->first();
    expect($attachment->file_name)->toBe('proof.pdf');
    expect($attachment->mime_type)->toBe('application/pdf');
    
    // Check file stored in storage
    Storage::disk('public')->assertExists($attachment->file_path);
});
