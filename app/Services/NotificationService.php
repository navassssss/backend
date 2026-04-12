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
        $versionKey = "notifications:user:{$user->id}:version";
        $version = Cache::get($versionKey, 1);
        
        $cacheKey = "notifications:user:{$user->id}:v{$version}";

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
        Cache::increment("notifications:user:{$userId}:version");
    }
}
