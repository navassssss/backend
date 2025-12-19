<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IssueResolved extends Notification
{
    use Queueable;

    public $issue;
    public $resolver;

    public function __construct(Issue $issue, User $resolver)
    {
        $this->issue = $issue;
        $this->resolver = $resolver;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Issue Resolved',
            'message' => "The issue '{$this->issue->title}' has been resolved by {$this->resolver->name}.",
            'action_url' => "/issues/{$this->issue->id}",
            'type' => 'success',
        ];
    }
}
