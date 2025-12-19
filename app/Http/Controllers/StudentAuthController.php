<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        // Try to find user by email first
        $user = User::where('email', $login)->first();

        // If not found by email, try by username through student relationship
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

        return response()->json([
            'token' => $token,
            'user' => $user,
            'student' => $user->student,
        ]);
    }

    /**
     * Get authenticated student info
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('student.class');

        return response()->json([
            'user' => $user,
            'student' => $user->student,
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
}
