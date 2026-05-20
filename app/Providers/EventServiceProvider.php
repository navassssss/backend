<?php

namespace App\Providers;

use App\Events\AchievementApproved;
use App\Events\AchievementRevoked;
use App\Listeners\UpdatePointsOnAchievementApproval;
use App\Listeners\RevokePointsOnAchievementRejection;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Events\AchievementApproved::class => [
            \App\Listeners\UpdatePointsOnAchievementApproval::class,
        ],
        \App\Events\AchievementRevoked::class => [
            \App\Listeners\RevokePointsOnAchievementRejection::class,
        ],
        \Illuminate\Notifications\Events\NotificationSent::class => [
            \App\Listeners\InvalidateNotificationCache::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
