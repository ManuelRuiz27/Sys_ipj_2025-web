<?php

namespace App\Policies;

use App\Models\Salud360Assignment;
use App\Models\User;

class Salud360AssignmentPolicy
{
    public function assign(User $user): bool
    {
        return $user->hasRole('encargado_360') && $user->can('s360.enc360.assign');
    }

    public function reassign(User $user, Salud360Assignment $assignment): bool
    {
        return $user->hasRole('encargado_360') && $user->can('s360.enc360.assign');
    }
}

