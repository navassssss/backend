<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\WebPushService;
use Illuminate\Support\Facades\Log;

class SendBulkPushNotification implements ShouldQueue
{
    use Queueable;

    public $userIds;
    public $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $userIds, array $payload)
    {
        $this->userIds = $userIds;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(WebPushService $push): void
    {
        $chunks = array_chunk($this->userIds, 100);

        // Fetch announcement from payload assuming payload contains announcement ID
        $announcementId = isset($this->payload['announcement_id']) ? $this->payload['announcement_id'] : null;
        $announcement = $announcementId ? \App\Models\Announcement::find($announcementId) : null;

        foreach ($chunks as $chunk) {
            $push->sendToUsersSilently($chunk, $this->payload);

            if ($announcement) {
                $users = \App\Models\User::whereIn('id', $chunk)->get();
                \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\NewAnnouncementNotification($announcement));
            }
        }

        Log::info("[WebPush] Bulk announcement push chunk sent to " . count($this->userIds) . " users.");
    }
}
