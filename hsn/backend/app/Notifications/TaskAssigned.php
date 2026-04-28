<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    use Queueable;

    public $task;
    public $assigner;

    public function __construct(Task $task, User $assigner)
    {
        $this->task = $task;
        $this->assigner = $assigner;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'New Task Assigned',
            'message' => "{$this->assigner->name} assigned you a new task: {$this->task->title}",
            'action_url' => "/tasks/{$this->task->id}",
            'type' => 'warning',
        ];
    }
}
