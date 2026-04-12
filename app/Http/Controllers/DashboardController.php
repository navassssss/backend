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
    public function __construct(
        private readonly \App\Services\DashboardService $dashboardService
    ) {}

    public function index()
    {
        $user = Auth::user();

        if (in_array($user->role, ['principal', 'manager'])) {
            return response()->json($this->dashboardService->getPrincipalDashboard());
        }

        return response()->json($this->dashboardService->getTeacherDashboard($user));
    }
}
