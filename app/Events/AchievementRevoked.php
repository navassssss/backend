<?php

namespace App\Events;

use App\Models\Achievement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementRevoked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $achievement;

    public function __construct(Achievement $achievement)
    {
        $this->achievement = $achievement;
    }
}
