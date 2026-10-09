<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** Anciennes permissions retirées : « enregistrer OEV » (remplacée par le circuit) et la gestion des demandes (remplacée par les signalements). */
    private const OBSOLETES = ['enregistrer OEV', 'voir demandes', 'valider demandes', 'rejeter demandes'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'voir utilisateurs',
            'créer utilisateurs',
            'modifier utilisateurs',
            'supprimer utilisateurs',
            'voir rapports',
            'gérer paramètres',
            'gérer rôles',
            // Signalements reçus du public : traités par le DP de la province, consultés par le DR de la région
            'voir signalements',
            'traiter signalements',
            // Circuit du dossier enfant : DP (constituer) → DR (valider) → niveau central (intégrer)
            'voir OEV',
            'constituer dossiers',
            'valider dossiers',
            'intégrer OEV',
            // Plaintes et avis des usagers : niveau central
            'voir plaintes',
            'traiter plaintes',
            // Parrainage : sessions et répertoire des parrains gérés par le niveau central, consultés par tous ;
            // appuis des partenaires enregistrés par le DP (sa province) et le niveau central
            'voir parrainage',
            'gérer sessions parrainage',
            'gérer parrains',
            'enregistrer appuis',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Permission::whereIn('name', self::OBSOLETES)->where('guard_name', 'web')->delete();

        $roles = [
            // Supervision : accès à tout, dans tout le pays
            'superAdmin' => $permissions,
            'administrateur' => $permissions,
            'responsable DGFE' => [
                'voir utilisateurs',
                'voir rapports',
                'voir OEV',
                'intégrer OEV',
                'voir plaintes',
                'traiter plaintes',
                'voir parrainage',
                'gérer sessions parrainage',
                'gérer parrains',
                'enregistrer appuis',
            ],
            'agent DGFE' => [
                'voir rapports',
                'voir OEV',
                'intégrer OEV',
                'voir plaintes',
                'traiter plaintes',
                'voir parrainage',
                'gérer sessions parrainage',
                'gérer parrains',
                'enregistrer appuis',
            ],
            'DR' => [
                'voir rapports',
                'voir signalements',
                'voir OEV',
                'valider dossiers',
                'voir parrainage',
            ],
            'DP' => [
                'voir rapports',
                'voir signalements',
                'traiter signalements',
                'voir OEV',
                'constituer dossiers',
                'voir parrainage',
                'enregistrer appuis',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
