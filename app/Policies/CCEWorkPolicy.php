<?php

namespace App\Policies;

use App\Models\CCEWork;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CCEWorkPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->isPrincipal() || $user->hasPermission('manage_cce')) {
            return true;
        }
    }

    public function viewAny(User $user)
    {
        return $user->isTeacher();
    }

    public function view(User $user, CCEWork $work)
    {
        if ($user->isTeacher()) {
            return $work->subject && $work->subject->teacher_id === $user->id;
        }
        return false;
    }

    public function create(User $user)
    {
        return $user->isTeacher();
    }

    public function update(User $user, CCEWork $work)
    {
        if ($user->isTeacher()) {
            return $work->subject && $work->subject->teacher_id === $user->id;
        }
        return false;
    }

    public function delete(User $user, CCEWork $work)
    {
        if ($user->isTeacher()) {
            return $work->subject && $work->subject->teacher_id === $user->id;
        }
        return false;
    }

    public function evaluate(User $user, CCEWork $work)
    {
        if ($user->isTeacher()) {
            return $work->subject && $work->subject->teacher_id === $user->id;
        }
        return false;
    }
}
