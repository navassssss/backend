<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IssueForwarded extends Notification
{
    use Queueable;

    public $issue;
    public $forwarder;

    public function __construct(Issue $issue, User $forwarder)
    {
        $this->issue = $issue;
        $this->forwarder = $forwarder;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Issue Forwarded',
            'message' => "{$this->forwarder->name} forwarded an issue to you: {$this->issue->title}",
            'action_url' => "/issues/{$this->issue->id}",
            'type' => 'issue',
        ];
    }
}
