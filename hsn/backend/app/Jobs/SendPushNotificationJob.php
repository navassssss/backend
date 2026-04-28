<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use App\Services\WebPushService;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 10;

    public $userId;
    public $payload;

    /**
     * Create a new job instance.
     *
     * @param int|string $userId
     * @param array $payload
     */
    public function __construct($userId, array $payload)
    {
        $this->userId = $userId;
        $this->payload = $payload;
    }

    /**
     * Determine the middleware the job should pass through.
     */
    public function middleware()
    {
        return [
            new RateLimited('push-notifications')
        ];
    }

    /**
     * Execute the job to dispatch standard synchronous push securely in the background.
     * 
     * @param \App\Services\WebPushService $push
     */
    public function handle(WebPushService $push): void
    {
        $push->sendToUser($this->userId, $this->payload);
    }
}
