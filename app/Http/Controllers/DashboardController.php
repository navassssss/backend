<?php

namespace App\Http\Controllers;

use App\Models\Duty;
use App\Models\Issue;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (in_array($user->role, ['principal', 'manager'])) {
            return $this->getPrincipalStats();
        }

        return $this->getTeacherStats($user);
    }

    private function getPrincipalStats()
    {
        $stats = [
            [
                'label' => 'Total Teachers',
                'value' => User::where('role', 'teacher')->count(),
                'icon' => 'Users',
                'color' => 'bg-primary-light text-primary'
            ],
            [
                'label' => 'Total Students',
                'value' => \App\Models\Student::count(),
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

        // Fetch tasks created by principal or generally upcoming? 
        // Showing recent uncompleted tasks for oversight
        $upcomingTasks = Task::where('status', 'pending')
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

        return response()->json([
            'stats' => $stats,
            'upcomingTasks' => $upcomingTasks
        ]);
    }

    private function getTeacherStats($user)
    {
        $stats = [
            [
                'label' => 'Pending Tasks',
                'value' => Task::where('assigned_to', $user->id)->where('status', 'pending')->count(),
                'icon' => 'Clock',
                'color' => 'bg-warning-light text-warning-foreground'
            ],
            [
                'label' => 'Active Duties',
                'value' => $user->duties()->where('status', 'active')->count(),
                'icon' => 'ClipboardList', // Changed from TrendingUp to reflect duties
                'color' => 'bg-success-light text-success'
            ],
            [
                'label' => 'Open Issues',
                'value' => Issue::where('responsible_user_id', $user->id)->whereIn('status', ['open', 'forwarded'])->count(),
                'icon' => 'AlertTriangle',
                'color' => 'bg-destructive-light text-destructive'
            ]
        ];

        $upcomingTasks = Task::where('status', 'pending')
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

        return response()->json([
            'stats' => $stats,
            'upcomingTasks' => $upcomingTasks
        ]);
    }
}
