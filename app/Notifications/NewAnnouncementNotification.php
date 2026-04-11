<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewAnnouncementNotification extends Notification
{
    use Queueable;

    public $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $url = $this->announcement->audience_type === 'students' ? '/student/announcements' : '/announcements';

        return [
            'type'    => 'info',
            'title'   => 'New Announcement',
            'message' => '📣 ' . $this->announcement->title,
            'action_url' => $url,
        ];
    }
}
