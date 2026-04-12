<?php

namespace App\Listeners;

use Illuminate\Notifications\Events\NotificationSent;
use App\Services\NotificationService;

class InvalidateNotificationCache
{
    /**
     * Create the event listener.
     *
     * @param NotificationService $notificationService
     */
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     *
     * @param NotificationSent $event
     */
    public function handle(NotificationSent $event): void
    {
        // The $event->notifiable is the entity (usually User) receiving the notification.
        // If it has an ID, we invalidate its highly-optimized notification cache explicitly.
        if (isset($event->notifiable->id)) {
            $this->notificationService->invalidateUserCache($event->notifiable->id);
        }
    }
}
