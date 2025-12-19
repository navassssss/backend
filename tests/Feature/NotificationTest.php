<?php

namespace Tests\Feature;

use App\Models\Duty;
use App\Models\Issue;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_forward_notification()
    {
        Notification::fake();

        $principal = User::factory()->create(['role' => 'principal']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        $issue = Issue::create([
            'title' => 'Test Issue',
            'description' => 'Test Desc',
            'priority' => 'high',
            'status' => 'open',
            'responsible_user_id' => $principal->id,
            'created_by' => $principal->id
        ]);

        $response = $this->actingAs($principal)->postJson("/api/issues/{$issue->id}/forward", [
            'to_user_id' => $teacher->id,
            'note' => 'Please handle this.'
        ]);

        $response->assertStatus(200);

        Notification::assertSentTo(
            [$teacher],
            \App\Notifications\IssueForwarded::class
        );
    }

    public function test_task_assigned_notification()
    {
        Notification::fake();

        $principal = User::factory()->create(['role' => 'principal']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $duty = Duty::create([
            'name' => 'Duty 1',
            'type' => 'responsibility',
            'frequency' => 'daily',
            'created_by' => $principal->id
        ]);

        $response = $this->actingAs($principal)->postJson("/api/tasks", [
            'title' => 'New Task',
            'duty_id' => $duty->id,
            'assigned_to' => $teacher->id,
            'scheduled_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertStatus(201);

        Notification::assertSentTo(
            [$teacher],
            \App\Notifications\TaskAssigned::class
        );
    }

    public function test_duty_assigned_notification()
    {
        Notification::fake();

        $principal = User::factory()->create(['role' => 'principal']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($principal)->postJson("/api/duties", [
            'name' => 'Morning Duty',
            'type' => 'rotational',
            'frequency' => 'daily',
            'teacher_ids' => [$teacher->id]
        ]);

        $response->assertStatus(201);

        Notification::assertSentTo(
            [$teacher],
            \App\Notifications\DutyAssigned::class
        );
    }

    public function test_report_reviewed_notification()
    {
        Notification::fake();

        $principal = User::factory()->create(['role' => 'principal']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $duty = Duty::create([
            'name' => 'Duty 1',
            'type' => 'responsibility',
            'frequency' => 'daily',
            'created_by' => $principal->id
        ]);
        
        $task = Task::create([
            'title' => 'Task 1',
            'duty_id' => $duty->id,
            'assigned_to' => $teacher->id,
            'scheduled_date' => now(),
            'status' => 'pending'
        ]);

        $report = Report::create([
            'task_id' => $task->id,
            'teacher_id' => $teacher->id,
            'description' => 'Done',
            'status' => 'submitted'
        ]);

        $response = $this->actingAs($principal)->postJson("/api/reports/{$report->id}/approve");

        $response->assertStatus(200);

        Notification::assertSentTo(
            [$teacher],
            \App\Notifications\ReportReviewed::class
        );
    }
    
    public function test_issue_resolved_notification()
    {
        Notification::fake();

        $creator = User::factory()->create(['role' => 'teacher']);
        $principal = User::factory()->create(['role' => 'principal']); // Resolver
        $responsible = User::factory()->create(['role' => 'teacher']); 
        
        $issue = Issue::create([
            'title' => 'Issue',
            'description' => 'Desc',
            'priority' => 'low',
            'created_by' => $creator->id,
            'responsible_user_id' => $responsible->id,
            'status' => 'open'
        ]);

        $response = $this->actingAs($principal)->postJson("/api/issues/{$issue->id}/resolve");

        $response->assertStatus(200);

        Notification::assertSentTo(
            [$creator, $responsible],
            \App\Notifications\IssueResolved::class
        );
    }
}
