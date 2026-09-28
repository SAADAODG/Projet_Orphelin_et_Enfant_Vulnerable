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
            // Circuit du dossier enfant : DP (constituer) → DR (valider) → niveau central (intégrer)
            'voir OEV',
            'constituer dossiers',
            'valider dossiers',
            'intégrer OEV',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Ancienne permission remplacée par le circuit ci-dessus
        Permission::where('name', 'enregistrer OEV')->where('guard_name', 'web')->delete();

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
                'constituer dossiers',
                'valider dossiers',
                'intégrer OEV',
            ],
            'responsable DGFE' => [
                'voir utilisateurs',
                'voir demandes',
                'valider demandes',
                'voir rapports',
                'voir OEV',
                'intégrer OEV',
            ],
            'agent DGFE' => [
                'voir demandes',
                'valider demandes',
                'voir rapports',
                'voir OEV',
                'intégrer OEV',
            ],
            'DR' => [
                'voir demandes',
                'valider demandes',
                'voir rapports',
                'voir OEV',
                'valider dossiers',
            ],
            'DP' => [
                'voir demandes',
                'voir rapports',
                'voir OEV',
                'constituer dossiers',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
