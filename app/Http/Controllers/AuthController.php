<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        
        // Check if user has a staff role
        $allowedRoles = ['teacher', 'principal', 'manager', 'admin'];
        if (!in_array($user->role, $allowedRoles)) {
            Auth::logout();
            return response()->json(['message' => 'Access denied. Staff portal is for staff members only.'], 403);
        }
        
        return [
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => $user
        ];
    }

    public function me(Request $request)
    {
        return $request->user();
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return ['message' => 'Logged out'];
    }
}
