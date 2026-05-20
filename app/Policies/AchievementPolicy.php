<?php

namespace App\Policies;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AchievementPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->isPrincipal()) {
            return true;
        }
    }

    public function viewAny(User $user)
    {
        return $user->hasPermission('review_achievements') || $user->isStudent();
    }

    public function create(User $user)
    {
        return $user->isStudent();
    }

    public function review(User $user)
    {
        return $user->hasPermission('review_achievements');
    }

    public function manageSettings(User $user)
    {
        return $user->hasPermission('review_achievements');
    }
}
