<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return $user->notifications->map(function ($notification) {
            $notification->created_at_human = \Illuminate\Support\Carbon::parse($notification->created_at)->inUserTimezone()->diffForHumans();
            $notification->created_at_formatted = \Illuminate\Support\Carbon::parse($notification->created_at)->inUserTimezone()->format('M d, Y h:i A');
            return $notification;
        });
    }

    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All marked as read']);
    }
}
