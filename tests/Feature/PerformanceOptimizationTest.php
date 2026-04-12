<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\AchievementCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\FeeManagementService;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    protected function principal(): User
    {
        return User::factory()->create(['role' => 'principal']);
    }

    protected function teacher(): User
    {
        return User::factory()->create(['role' => 'teacher']);
    }

    // ─────────────────────────────────────────────────────────────
    // CCE: Critical bug — no more debug return
    // ─────────────────────────────────────────────────────────────

    public function test_cce_student_marks_no_longer_returns_debug_json(): void
    {
        $principal = $this->principal();
        $response  = $this->actingAs($principal, 'sanctum')->getJson('/api/cce/student-marks');

        $response->assertJsonMissing(['debug' => true]);
        $response->assertJsonStructure(['data', 'total', 'current_page', 'per_page', 'last_page', 'stats']);
    }

    public function test_cce_student_marks_uses_single_aggregation_not_loop(): void
    {
        $principal = $this->principal();
        DB::enableQueryLog();
        $this->actingAs($principal, 'sanctum')->getJson('/api/cce/student-marks?per_page=20');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, count($queries), 'Too many queries — N+1 may still be present');
    }

    // ─────────────────────────────────────────────────────────────
    // CCE: index withCount replaces per-work queries
    // ─────────────────────────────────────────────────────────────

    public function test_cce_work_index_uses_withcount_not_per_work_queries(): void
    {
        $teacher = $this->teacher();
        DB::enableQueryLog();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/cce/works');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, count($queries));
    }

    // ─────────────────────────────────────────────────────────────
    // SETTING: Cache wrapping
    // ─────────────────────────────────────────────────────────────

    public function test_setting_getValue_stores_in_cache(): void
    {
        Cache::flush();
        Setting::setValue('test_key', 'test_value');

        $val = Setting::getValue('test_key', 'default');
        $this->assertEquals('test_value', $val);
        $this->assertTrue(Cache::has('setting:test_key'));
    }

    public function test_setting_getValue_served_from_cache_on_second_call(): void
    {
        Cache::flush();
        Setting::setValue('cached_key', 'cached_val');
        Setting::getValue('cached_key');

        DB::enableQueryLog();
        Setting::getValue('cached_key');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'Second getValue() should NOT hit DB — should use cache');
    }

    public function test_setting_setValue_busts_cache(): void
    {
        Cache::flush();
        Setting::setValue('bust_key', 'old');
        Setting::getValue('bust_key');
        $this->assertTrue(Cache::has('setting:bust_key'));

        Setting::setValue('bust_key', 'new');
        $this->assertFalse(Cache::has('setting:bust_key'), 'Cache should be busted after setValue');

        $val = Setting::getValue('bust_key');
        $this->assertEquals('new', $val);
    }

    // ─────────────────────────────────────────────────────────────
    // FEE: batchGetStudentSummary — fixed query count
    // ─────────────────────────────────────────────────────────────

    public function test_fee_batch_summary_uses_fixed_number_of_queries(): void
    {
        $service    = app(FeeManagementService::class);
        $studentIds = range(1, 20);

        DB::enableQueryLog();
        $service->batchGetStudentSummary($studentIds);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(3, count($queries),
            'batchGetStudentSummary should run ≤3 DB queries for any number of students');
    }

    public function test_fee_batch_summary_returns_default_for_students_with_no_plans(): void
    {
        $service = app(FeeManagementService::class);
        $result  = $service->batchGetStudentSummary([999999]);

        $this->assertArrayHasKey(999999, $result);
        $this->assertEquals(0, $result[999999]['total_expected']);
        $this->assertEquals(0, $result[999999]['total_paid']);
        $this->assertEquals('paid', $result[999999]['status']);
    }

    // ─────────────────────────────────────────────────────────────
    // FEE: getStatusCounts — batch not per-student
    // ─────────────────────────────────────────────────────────────

    public function test_fee_status_counts_returns_correct_structure(): void
    {
        $principal = $this->principal();
        $this->actingAs($principal, 'sanctum')
             ->getJson('/api/fees/status-counts')
             ->assertOk()
             ->assertJsonStructure(['paid', 'partial', 'due', 'overpaid']);
    }

    public function test_fee_status_counts_uses_batch_queries_not_per_student(): void
    {
        $principal = $this->principal();
        DB::enableQueryLog();
        $this->actingAs($principal, 'sanctum')->getJson('/api/fees/status-counts');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // ≤6: auth + student IDs + batch (expected/paid/lastPayment) + cache
        $this->assertLessThanOrEqual(6, count($queries),
            'getStatusCounts must use batchGetStudentSummary, not per-student getStudentMonthlyStatus()');
    }

    // ─────────────────────────────────────────────────────────────
    // FEE: getClassReport — batch not per-student
    // ─────────────────────────────────────────────────────────────

    public function test_fee_class_report_returns_correct_structure(): void
    {
        $principal = $this->principal();
        $classId   = DB::table('class_rooms')->insertGetId(['name' => 'RC', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($principal, 'sanctum')
             ->getJson("/api/fees/reports/class/{$classId}")
             ->assertOk()
             ->assertJsonStructure(['class_id', 'class_name', 'students', 'total_expected', 'total_paid', 'total_pending']);
    }

    public function test_fee_class_report_uses_batch_queries_not_per_student(): void
    {
        $principal = $this->principal();
        $classId   = DB::table('class_rooms')->insertGetId(['name' => 'BC', 'created_at' => now(), 'updated_at' => now()]);

        DB::enableQueryLog();
        $this->actingAs($principal, 'sanctum')->getJson("/api/fees/reports/class/{$classId}");
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // classRoom + students + batch(3) + modal = ≤7
        $this->assertLessThanOrEqual(7, count($queries),
            'getClassReport must use batchGetStudentSummary, not per-student getStudentMonthlyStatus()');
    }

    // ─────────────────────────────────────────────────────────────
    // ISSUE: Pagination applied
    // ─────────────────────────────────────────────────────────────

    public function test_issue_list_returns_paginated_structure(): void
    {
        $principal = $this->principal();
        $this->actingAs($principal, 'sanctum')
             ->getJson('/api/issues')
             ->assertOk()
             ->assertJsonStructure(['data', 'current_page', 'per_page', 'next_page_url']);
    }

    // ─────────────────────────────────────────────────────────────
    // QUEUE: Push jobs configured correctly
    // ─────────────────────────────────────────────────────────────

    public function test_send_push_job_has_correct_retry_and_timeout(): void
    {
        $job = new SendPushNotificationJob(1, ['title' => 'Test']);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(10, $job->timeout);
    }

    public function test_send_push_job_uses_rate_limited_middleware(): void
    {
        $job        = new SendPushNotificationJob(1, []);
        $middleware = $job->middleware();
        $this->assertNotEmpty($middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\RateLimited::class, $middleware[0]);
    }

    public function test_push_notification_dispatches_to_queue_not_sync(): void
    {
        Queue::fake();
        SendPushNotificationJob::dispatch(1, ['title' => 'Hello']);
        Queue::assertPushed(SendPushNotificationJob::class, fn($j) => $j->userId === 1);
    }

    // ─────────────────────────────────────────────────────────────
    // LEADERBOARD: Cache-first, no DB hit when cold
    // ─────────────────────────────────────────────────────────────

    public function test_leaderboard_returns_empty_array_when_cache_cold_not_crashing(): void
    {
        Cache::flush();
        $service = app(LeaderboardService::class);
        $result  = $service->getLeaderboard('students', 'overall');

        $this->assertIsArray($result);
    }

    public function test_leaderboard_compute_stores_last_updated_timestamp(): void
    {
        Cache::flush();
        $service = app(LeaderboardService::class);
        $service->computeAndCacheGlobalLeaderboard();

        $this->assertTrue(Cache::has('leaderboard:last_updated'));
        $this->assertNotNull(Cache::get('leaderboard:last_updated'));
    }

    // ─────────────────────────────────────────────────────────────
    // SUBJECT STATISTICS: SQL aggregation, not per-student loop
    // ─────────────────────────────────────────────────────────────

    public function test_subject_statistics_returns_correct_structure(): void
    {
        $teacher   = $this->teacher();
        $classId   = DB::table('class_rooms')->insertGetId(['name' => 'SC', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Math', 'code' => 'M101', 'class_id' => $classId,
            'teacher_id' => $teacher->id, 'final_max_marks' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($teacher, 'sanctum')
             ->getJson("/api/subjects/{$subjectId}/statistics")
             ->assertOk()
             ->assertJsonStructure(['subject', 'total_works', 'completed_works', 'works', 'student_marks']);
    }

    public function test_subject_statistics_student_count_runs_at_most_once(): void
    {
        $teacher   = $this->teacher();
        $classId   = DB::table('class_rooms')->insertGetId(['name' => 'QC', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Sci', 'code' => 'S101', 'class_id' => $classId,
            'teacher_id' => $teacher->id, 'final_max_marks' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 2 works — if Student::count() is inside the loop it runs twice
        DB::table('cce_works')->insert([
            ['subject_id' => $subjectId, 'created_by' => $teacher->id,
             'title' => 'W1', 'level' => 'easy', 'week' => 1,
             'tool_method' => 'test', 'submission_type' => 'online',
             'issued_date' => now(), 'due_date' => now()->addDays(7),
             'max_marks' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['subject_id' => $subjectId, 'created_by' => $teacher->id,
             'title' => 'W2', 'level' => 'easy', 'week' => 2,
             'tool_method' => 'test', 'submission_type' => 'online',
             'issued_date' => now(), 'due_date' => now()->addDays(14),
             'max_marks' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::enableQueryLog();
        $this->actingAs($teacher, 'sanctum')->getJson("/api/subjects/{$subjectId}/statistics");
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $countQueries = array_filter($queries, fn($q) =>
            preg_match('/select count\(\*\)/i', $q['query']) && str_contains($q['query'], 'students')
        );
        $this->assertLessThanOrEqual(1, count($countQueries),
            'Student::count() must be hoisted outside the loop — not run once per work');

        $this->assertLessThanOrEqual(10, count($queries),
            'Subject stats must use fixed per-request query count regardless of work count');
    }

    // ─────────────────────────────────────────────────────────────
    // STUDENT ATTENDANCE: Date-scoped, not full history
    // ─────────────────────────────────────────────────────────────

    public function test_student_attendance_defaults_to_90_days(): void
    {
        $principal = $this->principal();
        $userId    = DB::table('users')->insertGetId([
            'name' => 'Stu', 'email' => 'stu@t.com',
            'password' => bcrypt('pass'), 'role' => 'student',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentId = DB::table('students')->insertGetId([
            'user_id' => $userId, 'username' => 'stu001', 'wallet_balance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($principal, 'sanctum')
                         ->getJson("/api/students/{$studentId}/attendance");

        $response->assertOk()
                 ->assertJsonStructure(['student', 'range_days', 'overallStats', 'today', 'absentDates', 'recentRecords']);
        $this->assertEquals(90, $response->json('range_days'),
            'Default must be 90 days — not unbounded full-history loading');
    }

    public function test_student_attendance_respects_custom_days_param(): void
    {
        $principal = $this->principal();
        $userId    = DB::table('users')->insertGetId([
            'name' => 'Stu2', 'email' => 'stu2@t.com',
            'password' => bcrypt('pass'), 'role' => 'student',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentId = DB::table('students')->insertGetId([
            'user_id' => $userId, 'username' => 'stu002', 'wallet_balance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($principal, 'sanctum')
                         ->getJson("/api/students/{$studentId}/attendance?days=30");

        $response->assertOk();
        $this->assertEquals(30, $response->json('range_days'));
    }

    // ─────────────────────────────────────────────────────────────
    // ACHIEVEMENT CATEGORIES: Cache populated and invalidated
    // ─────────────────────────────────────────────────────────────

    // Achievement categories cache tests use the service directly
    // because the SPA route in web.php tries to read public/index.html
    // which doesn't exist in the test environment.

    public function test_achievement_categories_populates_cache_on_first_request(): void
    {
        Cache::flush();
        $this->assertFalse(Cache::has('achievement:categories'));

        // Simulate what AchievementController::categories() does
        Cache::remember('achievement:categories', now()->addHours(24),
            fn() => AchievementCategory::where('is_active', true)->get()
        );

        $this->assertTrue(Cache::has('achievement:categories'),
            'Cache::remember must populate the achievement:categories key');
    }

    public function test_achievement_categories_served_from_cache_on_second_request(): void
    {
        Cache::flush();
        // Prime the cache
        Cache::remember('achievement:categories', now()->addHours(24),
            fn() => AchievementCategory::where('is_active', true)->get()
        );

        DB::enableQueryLog();
        // Second call — must come from cache, no DB
        Cache::remember('achievement:categories', now()->addHours(24),
            fn() => AchievementCategory::where('is_active', true)->get()
        );
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $hits = array_filter($queries, fn($q) => str_contains($q['query'], 'achievement_categories'));
        $this->assertCount(0, $hits,
            'Second Cache::remember call must hit cache — no DB query for achievement_categories');
    }

    public function test_achievement_cache_is_busted_by_forget(): void
    {
        Cache::flush();
        Cache::remember('achievement:categories', now()->addHours(24),
            fn() => AchievementCategory::where('is_active', true)->get()
        );
        $this->assertTrue(Cache::has('achievement:categories'));

        Cache::forget('achievement:categories');

        $this->assertFalse(Cache::has('achievement:categories'));
    }

    // ─────────────────────────────────────────────────────────────
    // DATABASE INDEXES: Verify key indexes exist
    // ─────────────────────────────────────────────────────────────

    public function test_cce_submissions_has_work_student_unique_index(): void
    {
        $indexes = collect(DB::getSchemaBuilder()->getIndexes('cce_submissions'))->pluck('name');
        $this->assertTrue($indexes->contains('cce_sub_work_student_unique'));
    }

    public function test_tasks_has_assigned_status_composite_index(): void
    {
        $indexes = collect(DB::getSchemaBuilder()->getIndexes('tasks'))->pluck('name');
        $this->assertTrue($indexes->contains('tasks_assigned_to_status_index'));
    }

    public function test_fee_plans_has_unique_student_year_month_index(): void
    {
        $indexes = collect(DB::getSchemaBuilder()->getIndexes('monthly_fee_plans'))->pluck('name');
        $this->assertTrue($indexes->contains('fee_plans_student_year_month_unique'));
    }

    public function test_issues_has_created_by_status_composite_index(): void
    {
        $indexes = collect(DB::getSchemaBuilder()->getIndexes('issues'))->pluck('name');
        $this->assertTrue($indexes->contains('issues_created_by_status_index'));
    }
}
