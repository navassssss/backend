<?php

namespace App\Services;

use App\Models\Duty;
use App\Models\Issue;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    /**
     * Get Principal's cached dashboard payload.
     * Uses split caches to ensure stats can expire/refresh independently from upcoming tasks.
     *
     * @return array
     */
    public function getPrincipalDashboard()
    {
        $stats = Cache::remember('dashboard:stats:principal', now()->addMinutes(5)->addSeconds(random_int(0, 30)), function () {
            return [
                [
                    'label' => 'Total Teachers',
                    'value' => User::where('role', 'teacher')->count(),
                    'icon' => 'Users',
                    'color' => 'bg-primary-light text-primary'
                ],
                [
                    'label' => 'Total Students',
                    'value' => \App\Models\Student::academic()->count(),
                    'icon' => 'GraduationCap',
                    'color' => 'bg-info-light text-info'
                ],
                [
                    'label' => 'Active Tasks',
                    'value' => Task::where('status', '!=', 'completed')->count(), 
                    'icon' => 'CheckSquare',
                    'color' => 'bg-accent-light text-accent'
                ],
                [
                    'label' => 'Open Issues',
                    'value' => Issue::whereIn('status', ['open', 'forwarded'])->count(),
                    'icon' => 'AlertTriangle',
                    'color' => 'bg-destructive-light text-destructive'
                ],
                [
                    'label' => 'Pending Reports',
                    'value' => \App\Models\Report::where('status', 'submitted')->count(),
                    'icon' => 'FileText',
                    'color' => 'bg-warning-light text-warning-foreground'
                ],
                [
                    'label' => 'Pending Reviews',
                    'value' => \App\Models\Achievement::where('status', 'pending')->count(),
                    'icon' => 'Trophy',
                    'color' => 'bg-warning-light text-warning-foreground'
                ]
            ];
        });

        $upcomingTasks = Cache::remember('dashboard:recent_activity:principal', now()->addMinutes(2)->addSeconds(random_int(0, 30)), function () {
            return Task::where('status', 'pending')
                ->orderBy('scheduled_date')
                ->orderBy('scheduled_time')
                ->take(3)
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'time' => $task->scheduled_time ? date('h:i A', strtotime($task->scheduled_time)) : 'No time',
                        'status' => $task->status
                    ];
                });
        });

        return [
            'stats' => $stats,
            'upcomingTasks' => $upcomingTasks
        ];
    }

    /**
     * Get Teacher's personalized cached dashboard.
     * Caches per user ID to prevent data bleed.
     *
     * @param User $user
     * @return array
     */
    public function getTeacherDashboard(User $user)
    {
        $stats = Cache::remember("dashboard:stats:teacher:{$user->id}", now()->addMinutes(10)->addSeconds(random_int(0, 30)), function () use ($user) {
            return [
                [
                    'label' => 'Pending Tasks',
                    'value' => Task::where('assigned_to', $user->id)->where('status', 'pending')->count(),
                    'icon' => 'Clock',
                    'color' => 'bg-warning-light text-warning-foreground'
                ],
                [
                    'label' => 'Active Duties',
                    'value' => $user->duties()->where('status', 'active')->count(),
                    'icon'  => 'ClipboardList',
                    'color' => 'bg-success-light text-success'
                ],
                [
                    'label' => 'Open Issues',
                    'value' => Issue::where('responsible_user_id', $user->id)->whereIn('status', ['open', 'forwarded'])->count(),
                    'icon' => 'AlertTriangle',
                    'color' => 'bg-destructive-light text-destructive'
                ]
            ];
        });

        $upcomingTasks = Cache::remember("dashboard:recent_activity:teacher:{$user->id}", now()->addMinutes(2)->addSeconds(random_int(0, 30)), function () use ($user) {
            return Task::where('status', 'pending')
                ->where('assigned_to', $user->id)
                ->orderBy('scheduled_date')
                ->orderBy('scheduled_time')
                ->take(3)
                ->get()
                ->map(function ($task) {
                    return [
                        'id'     => $task->id,
                        'title'  => $task->title,
                        'time'   => $task->scheduled_time ? date('h:i A', strtotime($task->scheduled_time)) : 'No time',
                        'status' => $task->status,
                    ];
                });
        });

        return [
            'stats' => $stats,
            'upcomingTasks' => $upcomingTasks
        ];
    }
}
