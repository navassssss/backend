<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
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

    public function view(User $user)
    {
        return $user->isTeacher();
    }

    public function create(User $user)
    {
        return $user->isTeacher();
    }

    public function viewOperationalReport(User $user)
    {
        return $user->hasPermission('view_operational_report');
    }

    public function update(User $user, Attendance $attendance)
    {
        return $user->hasPermission('manage_attendance');
    }

    public function delete(User $user, Attendance $attendance)
    {
        return $user->hasPermission('manage_attendance');
    }
}
