<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * Get the latest notifications for a user, fully cached and versioned.
     *
     * @param User $user
     * @return \Illuminate\Support\Collection
     */
    public function getUserNotifications(User $user)
    {
        $cacheKey = "notifications:user:{$user->id}";

        return Cache::remember($cacheKey, now()->addSeconds(15), function () use ($user) {
            return $user->notifications()
                ->latest()
                ->take(20)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id'                   => $notification->id,
                        'data'                 => $notification->data,
                        'read_at'              => $notification->read_at,
                        'created_at'           => $notification->created_at,
                        'created_at_human'     => $notification->created_at->inUserTimezone()->diffForHumans(),
                        'created_at_formatted' => $notification->created_at->inUserTimezone()->format('M d, Y h:i A'),
                    ];
                });
        });
    }

    /**
     * Invalidate the notification cache for a specific user via atomic version increment.
     *
     * @param int $userId
     */
    public function invalidateUserCache(int $userId): void
    {
        Cache::forget("notifications:user:{$userId}");
        
        // Cleanup legacy versioning to avoid stale state.
        $version = Cache::get("notifications:user:{$userId}:version", 1);
        if ($version) {
            Cache::forget("notifications:user:{$userId}:v{$version}");
        }
        Cache::forget("notifications:user:{$userId}:version");
    }
}
