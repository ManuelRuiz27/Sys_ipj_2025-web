<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Salud360RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $encBienestar = Role::firstOrCreate(['name' => 'encargado_bienestar']);
        $enc360 = Role::firstOrCreate(['name' => 'encargado_360']);
        $psicologo = Role::firstOrCreate(['name' => 'psicologo']);

        // Permisos
        $perms = [
            's360.manage', // (admin)
            's360.enc_bienestar.manage', // crear/gestionar encargados360
            's360.enc360.assign', // asignar/cambiar psicólogo a beneficiario
            's360.enc360.view_dash',
            's360.enc_bienestar.view_dash',
            's360.psico.read_patients',
            's360.psico.create_session',
            's360.psico.view_history',
            's360.data.update_by_enc360', // solo encargado_360
        ];
        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Asignación por rol
        $admin->givePermissionTo(['s360.manage']);

        $encBienestar->givePermissionTo([
            's360.enc_bienestar.manage',
            's360.enc_bienestar.view_dash',
        ]);

        $enc360->givePermissionTo([
            's360.enc360.assign',
            's360.enc360.view_dash',
            's360.data.update_by_enc360',
        ]);

        $psicologo->givePermissionTo([
            's360.psico.read_patients',
            's360.psico.create_session',
            's360.psico.view_history',
        ]);
    }
}

