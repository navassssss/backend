<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly \App\Services\LeaderboardService $leaderboardService
    ) {}

    /**
     * Get student leaderboard (monthly or overall)
     */
    public function students(Request $request)
    {
        $type = $request->get('type', 'overall'); // monthly or overall
        return response()->json($this->leaderboardService->getLeaderboard('students', $type));
    }

    /**
     * Get class leaderboard (monthly or overall)
     */
    public function classes(Request $request)
    {
        $type = $request->get('type', 'overall'); // monthly or overall
        return response()->json($this->leaderboardService->getLeaderboard('classes', $type));
    }
}
