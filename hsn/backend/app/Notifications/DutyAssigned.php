<?php

namespace App\Notifications;

use App\Models\Duty;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DutyAssigned extends Notification
{
    use Queueable;

    public $duty;
    public $assigner;

    public function __construct(Duty $duty, User $assigner)
    {
        $this->duty = $duty;
        $this->assigner = $assigner;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'New Duty Assigned',
            'message' => "You have been assigned to the duty: {$this->duty->name}",
            'action_url' => "/duties/{$this->duty->id}",
            'type' => 'info',
        ];
    }
}
