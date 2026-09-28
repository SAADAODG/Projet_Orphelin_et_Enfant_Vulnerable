<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'voir utilisateurs',
            'créer utilisateurs',
            'modifier utilisateurs',
            'supprimer utilisateurs',
            'voir demandes',
            'valider demandes',
            'rejeter demandes',
            'voir rapports',
            'gérer paramètres',
            'gérer rôles',
            'voir OEV',
            'enregistrer OEV',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'superAdmin' => $permissions,
            'administrateur' => [
                'voir utilisateurs',
                'créer utilisateurs',
                'modifier utilisateurs',
                'supprimer utilisateurs',
                'voir demandes',
                'valider demandes',
                'rejeter demandes',
                'voir rapports',
                'gérer paramètres',
                'gérer rôles',
                'voir OEV',
                'enregistrer OEV',
            ],
            'responsable DGFE' => [
                'voir utilisateurs',
                'voir demandes',
                'valider demandes',
                'voir rapports',
                'voir OEV',
                'enregistrer OEV',
            ],
            'agent DGFE' => [
                'voir demandes',
                'valider demandes',
                'voir rapports',
                'voir OEV',
                'enregistrer OEV',
            ],
            'DR' => [
                'voir demandes',
                'valider demandes',
                'voir rapports',
                'voir OEV',
                'enregistrer OEV',
            ],
            'DP' => [
                'voir demandes',
                'voir rapports',
                'voir OEV',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
