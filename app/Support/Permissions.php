<?php

namespace App\Support;

/**
 * Noms des permissions de l'application (Spatie), en un seul endroit : contrôleurs, modèles, routes
 * (« 'can:' . Permissions::VOIR_OEV »). Le seeder des rôles (RolePermissionSeeder) et les vues Blade (@can)
 * gardent le nom en clair : un nom modifié ici doit l'être aussi là-bas.
 */
final class Permissions
{
    // Utilisateurs, rapports et administration
    public const VOIR_UTILISATEURS = 'voir utilisateurs';
    public const CREER_UTILISATEURS = 'créer utilisateurs';
    public const MODIFIER_UTILISATEURS = 'modifier utilisateurs';
    public const SUPPRIMER_UTILISATEURS = 'supprimer utilisateurs';
    public const VOIR_RAPPORTS = 'voir rapports';
    public const GERER_PARAMETRES = 'gérer paramètres';
    public const GERER_ROLES = 'gérer rôles';

    // Signalements reçus du public : traités par le DP de la province, consultés par le DR de la région
    public const VOIR_SIGNALEMENTS = 'voir signalements';
    public const TRAITER_SIGNALEMENTS = 'traiter signalements';

    // Circuit du dossier enfant : DP (constituer) → DR (valider) → niveau central (intégrer)
    public const VOIR_OEV = 'voir OEV';
    public const CONSTITUER_DOSSIERS = 'constituer dossiers';
    public const VALIDER_DOSSIERS = 'valider dossiers';
    public const INTEGRER_OEV = 'intégrer OEV';

    // Plaintes et avis des usagers : niveau central
    public const VOIR_PLAINTES = 'voir plaintes';
    public const TRAITER_PLAINTES = 'traiter plaintes';

    // Parrainage : sessions et répertoire des parrains gérés par le niveau central, consultés par tous ;
    // appuis des partenaires enregistrés par le DP (sa province) et le niveau central
    public const VOIR_PARRAINAGE = 'voir parrainage';
    public const GERER_SESSIONS_PARRAINAGE = 'gérer sessions parrainage';
    public const GERER_PARRAINS = 'gérer parrains';
    public const ENREGISTRER_APPUIS = 'enregistrer appuis';

    // Suivi de fin d'année des parrainés : le DP (sa province) et le niveau central
    public const SUIVRE_PARRAINAGES = 'suivre parrainages';

    // Journal d'audit : administrateurs (superAdmin et administrateur reçoivent toutes les permissions)
    public const VOIR_JOURNAL = 'voir journal';
}
