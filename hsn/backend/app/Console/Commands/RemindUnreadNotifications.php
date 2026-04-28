<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\WebPushService;

class RemindUnreadNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:remind-unread';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send push reminders for unread notifications older than 4 hours';

    /**
     * Execute the console command.
     */
    public function handle(WebPushService $push)
    {
        // Get all unread notifications exactly between 4 and 5 hours old.
        // Assumes this command runs hourly `->hourly()` in schedule.
        $unreads = DB::table('notifications')
            ->select('notifiable_id', DB::raw('count(*) as count'))
            ->whereNull('read_at')
            ->whereBetween('created_at', [now()->subHours(5), now()->subHours(4)])
            ->groupBy('notifiable_id')
            ->get();

        foreach ($unreads as $row) {
            $payload = [
                'title' => 'Reminder: Unread Notifications',
                'body'  => "You have {$row->count} unread notification(s) waiting for you.",
                'url'   => '/notifications',
                'tag'   => 'reminder'
            ];

            $push->sendToUser((int) $row->notifiable_id, $payload);
        }

        $this->info('Reminder pushes sent to ' . $unreads->count() . ' users.');
    }
}
