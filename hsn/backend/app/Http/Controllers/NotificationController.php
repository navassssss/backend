<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private readonly \App\Services\NotificationService $notificationService
    ) {}

    public function index()
    {
        $user = Auth::user();
        return response()->json($this->notificationService->getUserNotifications($user));
    }

    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        $this->notificationService->invalidateUserCache($user->id);

        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        $this->notificationService->invalidateUserCache($user->id);

        return response()->json(['message' => 'All marked as read']);
    }
}
