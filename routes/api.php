<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassRoomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DutyController;
use App\Http\Controllers\FeeManagementController;
use App\Http\Controllers\IssueCategoryController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MedicalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentAuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeacherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: VAPID key (frontend needs this before auth)
Route::get('/push/vapid-key', [PushSubscriptionController::class, 'vapidKey']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::post('/teachers', [TeacherController::class, 'store']);
    Route::get('/teachers/{id}', [TeacherController::class, 'show']);
    Route::put('/teachers/{id}', [TeacherController::class, 'update']);
    Route::post('/teachers/{teacher}/deactivate', [TeacherController::class, 'deactivate']);
    Route::post('/teachers/{teacher}/toggle-review-permission', [TeacherController::class, 'toggleReviewPermission']);
    Route::post('/teachers/{teacher}/toggle-vice-principal', [TeacherController::class, 'toggleVicePrincipal']);
    Route::post('/teachers/{teacher}/sync-permissions', [TeacherController::class, 'syncPermissions']);

    Route::get('/permissions', [\App\Http\Controllers\PermissionController::class, 'index']);

    Route::get('/students', [StudentController::class, 'index']);
    Route::post('/students/bulk', [StudentController::class, 'bulkCreate']);
    Route::post('/students/bulk-delete', [StudentController::class, 'bulkDelete']);
    Route::get('/students/{id}', [StudentController::class, 'showById']);
    Route::get('/students/{id}/attendance', [StudentController::class, 'getAttendance']);

    // Class management routes
    Route::get('/classes', [ClassRoomController::class, 'index']);
    Route::post('/classes/{id}/assign-teacher', [ClassRoomController::class, 'assignTeacher']);
    Route::delete('/classes/{id}/remove-teacher', [ClassRoomController::class, 'removeTeacher']);
    Route::get('/classes/{id}/report', [ClassRoomController::class, 'getReport']);
    Route::delete('/classes/{id}', [ClassRoomController::class, 'destroy']);




    Route::get('/duties', [DutyController::class, 'index']);
    Route::post('/duties', [DutyController::class, 'store']);
    Route::get('/duties/{duty}', [DutyController::class, 'show']);
    Route::put('/duties/{duty}', [DutyController::class, 'update']);
    Route::delete('/duties/{duty}', [DutyController::class, 'destroy']);

    Route::post('/duties/{duty}/assign-teachers', [DutyController::class, 'assignTeachers']);
    Route::post('/duties/{duty}/remove-teacher', [DutyController::class, 'removeTeacher']);

    /**
     * TASK ROUTES
     */
    Route::post('/tasks/bulk-delete', [TaskController::class, 'bulkDelete']);
    Route::get('/tasks', [TaskController::class, 'index']); // List tasks
    Route::post('/tasks', [TaskController::class, 'store']); // Create task (Principal)
    Route::get('/tasks/{task}', [TaskController::class, 'show']); // Task details
    Route::post('/tasks/{task}/complete', [TaskController::class, 'markComplete']); // Mark completed
    Route::get('/tasks/{task}/reports', [ReportController::class, 'reportsByTask']);


    /**
     * REPORT ROUTES
     */
    Route::get('/reports', [ReportController::class, 'index']); // List reports (Principal)
    Route::post('/reports', [ReportController::class, 'store']); // Submit report (Teacher)
    Route::get('/reports/{report}', [ReportController::class, 'show']); // Report details

    // Approve / Reject only by principal
    Route::post('/reports/{report}/approve', [ReportController::class, 'approve']);
    Route::post('/reports/{report}/reject', [ReportController::class, 'reject']);
    Route::post('/reports/{report}/review-note', [ReportController::class, 'addReviewNote']);
    Route::post('/reports/{report}/comment', [ReportController::class, 'addComment']);



Route::get('/issue-categories', [IssueCategoryController::class, 'index']);
    Route::post('/issue-categories', [IssueCategoryController::class, 'store']);
    Route::put('/issue-categories/{category}', [IssueCategoryController::class, 'update']);
    Route::delete('/issue-categories/{category}', [IssueCategoryController::class, 'destroy']);

    // Issues
    Route::get('/issues', [IssueController::class, 'index']);
    Route::post('/issues', [IssueController::class, 'store']);
    Route::get('/issues/{issue}', [IssueController::class, 'show']);
    Route::post('/issues/{issue}/comment', [IssueController::class, 'addComment']);
    Route::post('/issues/{issue}/forward', [IssueController::class, 'forward']);
    Route::post('/issue-categories', [IssueCategoryController::class, 'store']);
    Route::post('/issues/{issue}/resolve', [IssueController::class, 'resolve']);
    Route::post('/issue-categories', [IssueCategoryController::class, 'store']);
    Route::post('/issues/{issue}/resolve', [IssueController::class, 'resolve']);


    // Notifications
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead']);

    // Announcements (staff management)
    Route::get('/announcements', [App\Http\Controllers\AnnouncementController::class, 'index']);
    Route::post('/announcements', [App\Http\Controllers\AnnouncementController::class, 'store']);
    Route::get('/announcements/{announcement}', [App\Http\Controllers\AnnouncementController::class, 'show']);
    Route::put('/announcements/{announcement}', [App\Http\Controllers\AnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [App\Http\Controllers\AnnouncementController::class, 'destroy']);
    Route::post('/announcements/{announcement}/toggle-pin', [App\Http\Controllers\AnnouncementController::class, 'togglePin']);
    // Teacher announcement feed (announcements targeted at the logged-in teacher)
    Route::get('/announcements/feed/teacher', [App\Http\Controllers\AnnouncementController::class, 'teacherFeed']);
    Route::post('/announcements/{announcement}/read', [App\Http\Controllers\AnnouncementController::class, 'teacherMarkRead']);

    // ── Medical Records ──────────────────────────────────────
    Route::get('/medical/active',                  [MedicalController::class, 'active']);
    Route::get('/medical/history',                 [MedicalController::class, 'history']);
    Route::post('/medical',                        [MedicalController::class, 'store']);
    Route::get('/medical/{medical}',               [MedicalController::class, 'show']);
    Route::post('/medical/{medical}/recover',      [MedicalController::class, 'recover']);
    Route::post('/medical/{medical}/sent-home',    [MedicalController::class, 'sentHome']);
    Route::patch('/medical/{medical}/toggle-doctor', [MedicalController::class, 'toggleDoctor']);

    /**
     * ATTENDANCE ROUTES
     */
    Route::get('/attendance', [App\Http\Controllers\AttendanceController::class, 'index']);
    Route::post('/attendance', [App\Http\Controllers\AttendanceController::class, 'store']);
    Route::post('/attendance/check', [App\Http\Controllers\AttendanceController::class, 'check']);
    // Specific routes must come before dynamic routes
    Route::get('/attendance/classes', [App\Http\Controllers\AttendanceController::class, 'classes']);
    Route::get('/attendance/{id}', [App\Http\Controllers\AttendanceController::class, 'show']);
    // Route::get('/classes', [App\Http\Controllers\AttendanceController::class, 'classes']); // Commented out - conflicts with ClassRoomController
    Route::get('/classes/{classId}/students', [App\Http\Controllers\AttendanceController::class, 'students']);

    /**
     * CCE ROUTES
     */
    // Subjects
    Route::get('/subjects', [App\Http\Controllers\SubjectController::class, 'index']);
    Route::post('/subjects/bulk', [App\Http\Controllers\SubjectController::class, 'bulkCreate']);
    Route::post('/subjects', [App\Http\Controllers\SubjectController::class, 'store']);
    Route::get('/subjects/{id}', [App\Http\Controllers\SubjectController::class, 'show']);
    Route::get('/subjects/{id}/statistics', [App\Http\Controllers\SubjectController::class, 'getSubjectStatistics']);
    Route::put('/subjects/{id}', [App\Http\Controllers\SubjectController::class, 'update']);
    Route::delete('/subjects/{id}', [App\Http\Controllers\SubjectController::class, 'destroy']);
    Route::post('/subjects/{id}/toggle-lock', [App\Http\Controllers\SubjectController::class, 'toggleLock']);

    // CCE Works
    Route::get('/cce/works', [App\Http\Controllers\CCEWorkController::class, 'index']);
    Route::post('/cce/works', [App\Http\Controllers\CCEWorkController::class, 'store']);
    Route::get('/cce/works/{id}', [App\Http\Controllers\CCEWorkController::class, 'show']);
    Route::put('/cce/works/{id}', [App\Http\Controllers\CCEWorkController::class, 'update']);
    Route::delete('/cce/works/{id}', [App\Http\Controllers\CCEWorkController::class, 'destroy']);

    // CCE Submissions (Teacher)
    Route::post('/cce/submissions/bulk-evaluate', [App\Http\Controllers\CCESubmissionController::class, 'bulkEvaluate']);
    Route::post('/cce/submissions/{id}/evaluate', [App\Http\Controllers\CCESubmissionController::class, 'evaluate']);
    
    // CCE Student Marks (Principal)
    Route::get('/cce/student-marks', [App\Http\Controllers\CCESubmissionController::class, 'studentMarks']);
    
    // CCE Class Report (Principal)
    Route::get('/cce/class-report', [App\Http\Controllers\CCEWorkController::class, 'getClassReport']);

    /**
     * FEE MANAGEMENT ROUTES
     */
    // Students & Overview
    Route::get('/fees/students', [FeeManagementController::class, 'getStudents']);
    Route::get('/fees/status-counts', [FeeManagementController::class, 'getStatusCounts']);
    Route::get('/fees/students/{studentId}', [FeeManagementController::class, 'getStudentOverview']);
    Route::get('/fees/students/{studentId}/payments', [FeeManagementController::class, 'getPaymentHistory']);
    
    // Payments
    Route::post('/fees/payments', [FeeManagementController::class, 'addPayment']);
    Route::post('/fees/payments/{paymentId}/receipt', [FeeManagementController::class, 'toggleReceipt']);
    
    // Fee Plans
    Route::post('/fees/plans/class', [FeeManagementController::class, 'setClassFee']);
    Route::post('/fees/plans/student', [FeeManagementController::class, 'setStudentFeeRange']);
    Route::post('/fees/students/{studentId}/monthly-fee', [FeeManagementController::class, 'updateStudentMonthlyFee']);
    
    // Reports
    Route::get('/fees/reports/summary', [FeeManagementController::class, 'getSummary']);
    Route::get('/fees/reports/class/{classId}', [FeeManagementController::class, 'getClassReport']);
    Route::get('/fees/reports/daily/{date}', [FeeManagementController::class, 'getDailyReport']);
    
    // Fee Utilities
    Route::get('/fees/classes', [FeeManagementController::class, 'getClasses']);

    // ── Push Notifications ──────────────────────────────────────
    Route::post('/push/subscribe',   [PushSubscriptionController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);
    Route::post('/push/test',        [PushSubscriptionController::class, 'test']);
    Route::post('/push/send',        [PushSubscriptionController::class, 'send']);
    // ── Outpass Management ──────────────────────────────────────
    Route::get('/outpasses/dashboard', [App\Http\Controllers\OutpassController::class, 'dashboard']);
    Route::get('/outpasses', [App\Http\Controllers\OutpassController::class, 'index']);
    Route::post('/outpasses', [App\Http\Controllers\OutpassController::class, 'store']);
    Route::put('/outpasses/{outpass}/checkin', [App\Http\Controllers\OutpassController::class, 'checkin']);
});

/**
 * STUDENT PORTAL ROUTES
 */

// Public leaderboards (no auth required)
Route::get('/leaderboard/students', [LeaderboardController::class, 'students']);
Route::get('/leaderboard/classes', [LeaderboardController::class, 'classes']);

// Public student profile
Route::get('/students/{username}', [StudentController::class, 'show']);

// Student authentication
Route::post('/student/login', [StudentAuthController::class, 'login']);

// Protected student routes
Route::middleware('auth:sanctum')->prefix('student')->group(function () {
    // Auth
    Route::get('/me', [StudentAuthController::class, 'me']);
    Route::post('/logout', [StudentAuthController::class, 'logout']);

    // Profile
    Route::get('/profile', [StudentController::class, 'profile']);
    Route::put('/profile', [StudentController::class, 'update']);

    // Achievements
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::post('/achievements', [AchievementController::class, 'store']);
    Route::get('/achievement-categories', [AchievementController::class, 'categories']);

    // Student Announcements
    Route::get('/announcements', [App\Http\Controllers\AnnouncementController::class, 'studentIndex']);
    Route::post('/announcements/{announcement}/read', [App\Http\Controllers\AnnouncementController::class, 'markRead']);

    // Transactions
    Route::get('/transactions', [App\Http\Controllers\StudentTransactionController::class, 'index']);
    
    // Attendance
    Route::get('/attendance', [App\Http\Controllers\StudentAttendanceController::class, 'index']);

    // CCE
    Route::get('/cce/works', [App\Http\Controllers\CCESubmissionController::class, 'studentWorks']);
    Route::post('/cce/submissions/{id}/submit', [App\Http\Controllers\CCESubmissionController::class, 'submitWork']);
});

// Principal-only routes for achievement management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/achievement-settings', [App\Http\Controllers\AchievementSettingsController::class, 'index']);
    Route::post('/achievement-settings/categories', [App\Http\Controllers\AchievementSettingsController::class, 'storeCategory']);
    Route::put('/achievement-settings/categories/{id}', [App\Http\Controllers\AchievementSettingsController::class, 'updateCategory']);
    Route::delete('/achievement-settings/categories/{id}', [App\Http\Controllers\AchievementSettingsController::class, 'destroyCategory']);
    Route::post('/achievement-settings/thresholds', [App\Http\Controllers\AchievementSettingsController::class, 'updateThresholds']);

    Route::get('/achievements', [AchievementController::class, 'all']); // All achievements for review
    Route::post('/achievements/{achievement}/approve', [AchievementController::class, 'approve']);
    Route::post('/achievements/{achievement}/reject', [AchievementController::class, 'reject']);
});
