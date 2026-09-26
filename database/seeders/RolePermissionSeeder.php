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
            ],
            'responsable DGFE' => [
                'voir utilisateurs',
                'voir demandes',
                'valider demandes',
                'voir rapports',
            ],
            'agent DGFE' => [
                'voir demandes',
                'valider demandes',
                'voir rapports',
            ],
            'DR' => [
                'voir demandes',
                'valider demandes',
                'voir rapports',
            ],
            'DP' => [
                'voir demandes',
                'voir rapports',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
