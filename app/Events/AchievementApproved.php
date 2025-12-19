<?php

namespace App\Events;

use App\Models\Achievement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public Achievement $achievement)
    {
    }
}
