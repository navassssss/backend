<?php

namespace App\Policies;

use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicalPolicy
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

    public function view(User $user, MedicalRecord $record)
    {
        return $user->isTeacher();
    }

    public function create(User $user)
    {
        return $user->isTeacher();
    }

    public function update(User $user, MedicalRecord $record)
    {
        return $user->isTeacher();
    }

    public function delete(User $user, MedicalRecord $record)
    {
        return $user->hasPermission('manage_medical');
    }
}
