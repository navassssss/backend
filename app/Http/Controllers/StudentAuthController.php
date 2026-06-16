<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Models\Setting;

class StudentAuthController extends Controller
{
    /**
     * Student login
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string', // Can be email or username
            'password' => 'required|string',
        ]);

        $login = $request->login;
        $password = $request->password;

        // Try to find user by email or username first
        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        // If not found by email or username, try by username through student relationship
        if (!$user) {
            $student = Student::where('username', $login)->first();
            if ($student) {
                $user = $student->user;
            }
        }

        // Verify user exists, has student role, and password is correct
        if (!$user || $user->role !== 'student' || !Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Attempt authentication
        Auth::login($user);

        // Create token
        $token = $user->createToken('student-auth-token')->plainTextToken;

        // Load student data with relationships
        $user->load('student.class');

        $student = $user->student;
        
        $userData = $user->only([
            'id', 'name', 'email', 'username', 'phone', 'role', 
            'is_vice_principal', 'department', 'avatar'
        ]);

        return response()->json([
            'token' => $token,
            'user' => $userData,
            'student' => $student ? $this->buildStudentData($student) : null,
        ]);
    }

    /**
     * Get authenticated student info
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('student.class');

        $student = $user->student;

        $userData = $user->only([
            'id', 'name', 'email', 'username', 'phone', 'role', 
            'is_vice_principal', 'department', 'avatar'
        ]);

        return response()->json([
            'user' => $userData,
            'student' => $student ? $this->buildStudentData($student) : null,
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Build enriched student data including star-progress info.
     */
    private function buildStudentData(Student $student): array
    {
        // Strictly filter the base attributes needed for frontend
        $data = $student->only([
            'id', 'class_id', 'department_id', 'username', 'roll_number', 'photo', 
            'total_points', 'wallet_balance', 'monthly_fee', 'is_hifz', 'stars', 'monthly_points'
        ]);
        
        // Ensure computed name is included if present
        $data['name'] = $student->name;
        
        // Strictly filter class relation if loaded
        if ($student->relationLoaded('class') && $student->class) {
            $data['class'] = $student->class->only([
                'id', 'name', 'department', 'total_points', 'is_hifz'
            ]);
        }

        // ── Star Thresholds ──────────────────────────────────────────────
        $thresholdsJson = Cache::remember('star_thresholds', 3600, function () {
            return Setting::getValue('star_thresholds');
        });

        $thresholds = [];
        if ($thresholdsJson) {
            $decoded = json_decode($thresholdsJson, true);
            if (is_array($decoded)) {
                $thresholds = $decoded;
            }
        }

        // Default: 1 star per 20 points if no settings defined yet
        $usingDefault = empty($thresholds);

        $totalPoints = (int) ($student->total_points ?? 0);
        $currentStars = (int) ($student->stars ?? 0);

        if ($usingDefault) {
            // Simple uniform model
            $pointsInCurrentStar = $totalPoints % 20;
            $nextStarPoints      = ($currentStars + 1) * 20;
            $currentStarPoints   = $currentStars * 20;
        } else {
            // Dynamic thresholds: keys are star counts (1,2,3…), values are points required
            ksort($thresholds); // ensure ascending order

            // Find current star threshold (floor) and next star threshold (ceiling)
            $currentStarPoints = 0;
            $nextStarPoints    = null;

            foreach ($thresholds as $starCount => $pts) {
                if ($totalPoints >= $pts) {
                    $currentStarPoints = $pts;
                } else {
                    // First threshold the student hasn't reached = next target
                    if ($nextStarPoints === null) {
                        $nextStarPoints = $pts;
                    }
                }
            }

            if ($nextStarPoints === null) {
                // Student has surpassed all defined thresholds – already at max star
                $nextStarPoints = $currentStarPoints; // no next milestone
            }

            $pointsInCurrentStar = $totalPoints - $currentStarPoints;
        }

        $pointsToNextStar = max(0, $nextStarPoints - $totalPoints);
        $bandWidth        = max(1, $nextStarPoints - $currentStarPoints);
        $progressPct      = (int) round(($pointsInCurrentStar / $bandWidth) * 100);
        $progressPct      = min(100, max(0, $progressPct));

        $data['star_progress'] = [
            'current_stars'       => $currentStars,
            'total_points'        => $totalPoints,
            'current_star_points' => $currentStarPoints,
            'next_star_points'    => $nextStarPoints,
            'points_in_band'      => $pointsInCurrentStar,
            'points_to_next_star' => $pointsToNextStar,
            'progress_pct'        => $progressPct,
            'thresholds'          => $thresholds,
        ];

        return $data;
    }
}
