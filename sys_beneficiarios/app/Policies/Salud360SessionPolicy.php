<?php

namespace App\Policies;

use App\Models\Beneficiario;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use App\Models\User;

class Salud360SessionPolicy
{
    // Crear sesión para un beneficiario asignado al psicólogo
    public function create(User $user, Beneficiario $beneficiario): bool
    {
        if (! $user->hasRole('psicologo')) {
            return false;
        }
        return Salud360Assignment::where('beneficiario_id', $beneficiario->id)
            ->where('psicologo_id', $user->id)
            ->where('active', true)
            ->exists();
    }

    // Ver sesión
    public function view(User $user, Salud360Session $session): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('encargado_360') || $user->hasRole('encargado_bienestar')) {
            return true;
        }
        if ($user->hasRole('psicologo')) {
            // Psicólogo puede ver si es el dueño o está asignado al beneficiario
            if ($session->psicologo_id === $user->id) {
                return true;
            }
            return Salud360Assignment::where('beneficiario_id', $session->beneficiario_id)
                ->where('psicologo_id', $user->id)
                ->where('active', true)
                ->exists();
        }
        return false;
    }

    // Actualizar sesión (solo encargado_360 con permiso específico)
    public function update(User $user, Salud360Session $session): bool
    {
        return $user->hasRole('encargado_360') && $user->can('s360.data.update_by_enc360');
    }
}

