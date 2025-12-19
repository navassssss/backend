<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DutyController;
use App\Http\Controllers\FeeManagementController;
use App\Http\Controllers\IssueCategoryController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentAuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeacherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::post('/teachers', [TeacherController::class, 'store']);
    Route::get('/teachers/{id}', [TeacherController::class, 'show']);
    Route::post('/teachers/{teacher}/deactivate', [TeacherController::class, 'deactivate']);
    Route::post('/teachers/{teacher}/toggle-review-permission', [TeacherController::class, 'toggleReviewPermission']);


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

    /**
     * ATTENDANCE ROUTES
     */
    Route::get('/attendance', [App\Http\Controllers\AttendanceController::class, 'index']);
    Route::post('/attendance', [App\Http\Controllers\AttendanceController::class, 'store']);
    Route::post('/attendance/check', [App\Http\Controllers\AttendanceController::class, 'check']);
    Route::get('/attendance/{id}', [App\Http\Controllers\AttendanceController::class, 'show']);
    Route::get('/classes', [App\Http\Controllers\AttendanceController::class, 'classes']);
    Route::get('/classes/{classId}/students', [App\Http\Controllers\AttendanceController::class, 'students']);

    /**
     * CCE ROUTES
     */
    // Subjects
    Route::get('/subjects', [App\Http\Controllers\SubjectController::class, 'index']);
    Route::post('/subjects', [App\Http\Controllers\SubjectController::class, 'store']);
    Route::get('/subjects/{id}', [App\Http\Controllers\SubjectController::class, 'show']);
    Route::put('/subjects/{id}', [App\Http\Controllers\SubjectController::class, 'update']);
    Route::post('/subjects/{id}/toggle-lock', [App\Http\Controllers\SubjectController::class, 'toggleLock']);

    // CCE Works
    Route::get('/cce/works', [App\Http\Controllers\CCEWorkController::class, 'index']);
    Route::post('/cce/works', [App\Http\Controllers\CCEWorkController::class, 'store']);
    Route::get('/cce/works/{id}', [App\Http\Controllers\CCEWorkController::class, 'show']);
    Route::put('/cce/works/{id}', [App\Http\Controllers\CCEWorkController::class, 'update']);
    Route::delete('/cce/works/{id}', [App\Http\Controllers\CCEWorkController::class, 'destroy']);

    // CCE Submissions (Teacher)
    Route::post('/cce/submissions/{id}/evaluate', [App\Http\Controllers\CCESubmissionController::class, 'evaluate']);
    
    // CCE Student Marks (Principal)
    Route::get('/cce/student-marks', [App\Http\Controllers\CCESubmissionController::class, 'studentMarks']);

    /**
     * FEE MANAGEMENT ROUTES
     */
    // Students & Overview
    Route::get('/fees/students', [FeeManagementController::class, 'getStudents']);
    Route::get('/fees/students/{studentId}', [FeeManagementController::class, 'getStudentOverview']);
    Route::get('/fees/students/{studentId}/payments', [FeeManagementController::class, 'getPaymentHistory']);
    
    // Payments
    Route::post('/fees/payments', [FeeManagementController::class, 'addPayment']);
    Route::post('/fees/payments/{paymentId}/receipt', [FeeManagementController::class, 'toggleReceipt']);
    
    // Fee Plans
    Route::post('/fees/plans/class', [FeeManagementController::class, 'setClassFee']);
    Route::post('/fees/plans/student', [FeeManagementController::class, 'setStudentFeeRange']);
    
    // Reports
    Route::get('/fees/reports/summary', [FeeManagementController::class, 'getSummary']);
    Route::get('/fees/reports/class/{classId}', [FeeManagementController::class, 'getClassReport']);
    Route::get('/fees/reports/daily/{date}', [FeeManagementController::class, 'getDailyReport']);
    
    // Utilities
    Route::get('/fees/classes', [FeeManagementController::class, 'getClasses']);
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
    Route::get('/achievements', [AchievementController::class, 'all']); // All achievements for review
    Route::post('/achievements/{achievement}/approve', [AchievementController::class, 'approve']);
    Route::post('/achievements/{achievement}/reject', [AchievementController::class, 'reject']);
});

