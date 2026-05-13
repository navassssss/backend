<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required']
        ]);

        $login = $request->input('email');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (!Auth::attempt([$field => $login, 'password' => $request->input('password')])) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        
        // Check if user has a staff role
        $allowedRoles = ['teacher', 'principal', 'manager', 'admin'];
        if (!in_array($user->role, $allowedRoles)) {
            Auth::logout();
            return response()->json(['message' => 'Access denied. Staff portal is for staff members only.'], 403);
        }
        
        $user->load('permissions');

        return [
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => $user
        ];
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('permissions');
        return $user;
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'phone'      => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'bio'        => 'nullable|string|max:1000',
        ]);

        $user->update($validated);

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return ['message' => 'Logged out'];
    }
}
