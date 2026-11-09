<?php

namespace Database\Seeders;

use App\Support\Permissions as P;
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

        // Noms des permissions : App\Support\Permissions (un seul endroit pour toute l'application)
        $permissions = [
            P::VOIR_UTILISATEURS,
            P::CREER_UTILISATEURS,
            P::MODIFIER_UTILISATEURS,
            P::SUPPRIMER_UTILISATEURS,
            P::VOIR_RAPPORTS,
            P::GERER_PARAMETRES,
            P::GERER_ROLES,
            // Signalements reçus du public : traités par le DP de la province, consultés par le DR de la région
            P::VOIR_SIGNALEMENTS,
            P::TRAITER_SIGNALEMENTS,
            // Circuit du dossier enfant : DP (constituer) → DR (valider) → niveau central (intégrer)
            P::VOIR_OEV,
            P::CONSTITUER_DOSSIERS,
            P::VALIDER_DOSSIERS,
            P::INTEGRER_OEV,
            // Plaintes et avis des usagers : niveau central
            P::VOIR_PLAINTES,
            P::TRAITER_PLAINTES,
            // Parrainage : sessions et répertoire des parrains gérés par le niveau central, consultés par tous ;
            // appuis des partenaires enregistrés par le DP (sa province) et le niveau central
            P::VOIR_PARRAINAGE,
            P::GERER_SESSIONS_PARRAINAGE,
            P::GERER_PARRAINS,
            P::ENREGISTRER_APPUIS,
            // Suivi de fin d'année des parrainés : le DP (sa province) et le niveau central
            P::SUIVRE_PARRAINAGES,
            // Journal d'audit : administrateurs (superAdmin et administrateur reçoivent toutes les permissions)
            P::VOIR_JOURNAL,
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
                P::VOIR_UTILISATEURS,
                P::VOIR_RAPPORTS,
                P::VOIR_OEV,
                P::INTEGRER_OEV,
                P::VOIR_PLAINTES,
                P::TRAITER_PLAINTES,
                P::VOIR_PARRAINAGE,
                P::GERER_SESSIONS_PARRAINAGE,
                P::GERER_PARRAINS,
                P::ENREGISTRER_APPUIS,
                P::SUIVRE_PARRAINAGES,
            ],
            'agent DGFE' => [
                P::VOIR_RAPPORTS,
                P::VOIR_OEV,
                P::INTEGRER_OEV,
                P::VOIR_PLAINTES,
                P::TRAITER_PLAINTES,
                P::VOIR_PARRAINAGE,
                P::GERER_SESSIONS_PARRAINAGE,
                P::GERER_PARRAINS,
                P::ENREGISTRER_APPUIS,
                P::SUIVRE_PARRAINAGES,
            ],
            'DR' => [
                P::VOIR_RAPPORTS,
                P::VOIR_SIGNALEMENTS,
                P::VOIR_OEV,
                P::VALIDER_DOSSIERS,
                P::VOIR_PARRAINAGE,
            ],
            'DP' => [
                P::VOIR_RAPPORTS,
                P::VOIR_SIGNALEMENTS,
                P::TRAITER_SIGNALEMENTS,
                P::VOIR_OEV,
                P::CONSTITUER_DOSSIERS,
                P::VOIR_PARRAINAGE,
                P::ENREGISTRER_APPUIS,
                P::SUIVRE_PARRAINAGES,
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
