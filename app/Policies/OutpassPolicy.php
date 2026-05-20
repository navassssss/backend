<?php

namespace App\Policies;

use App\Models\Outpass;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OutpassPolicy
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
        return $user->isTeacher();
    }

    public function view(User $user, Outpass $outpass)
    {
        return $user->isTeacher();
    }

    public function create(User $user)
    {
        return $user->hasPermission('manage_outpasses');
    }

    public function update(User $user, Outpass $outpass)
    {
        return $user->hasPermission('manage_outpasses');
    }

    public function delete(User $user, Outpass $outpass)
    {
        return $user->hasPermission('manage_outpasses');
    }
}
