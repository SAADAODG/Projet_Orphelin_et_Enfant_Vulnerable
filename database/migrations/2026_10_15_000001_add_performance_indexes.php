<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index des colonnes de filtre, de comptage et de tri à fort volume (mesurés sur 1 000 000 de dossiers,
 * scripts/perf).
 *
 * - PostgreSQL n'indexe pas les clés étrangères : sans index, le cloisonnement par zone (région, province)
 *   parcourt toute la table oevs.
 * - Les comptages des tableaux de bord et des listes (par état, zone, statut, sexe…) se font dans l'index
 *   « comptages » sans lire la table (index-only scan) ; il est partiel (dossiers non supprimés), comme
 *   toutes les requêtes de l'application (suppression logique).
 * - Les listes triées par date d'un état (soumis, validés, intégrés…) lisent l'index dans l'ordre et
 *   s'arrêtent à la page affichée.
 */
return new class extends Migration
{
    private const INDEX = [
        'oevs' => [
            ['region_id', 'statut_dossier'],
            ['province_id', 'statut_dossier'],
            ['commune_id'],
            ['statut_dossier', 'updated_at'],
            ['statut_dossier', 'soumis_at'],
            ['statut_dossier', 'verifie_at'],
            ['statut_dossier', 'complement_at'],
            ['statut_dossier', 'rejete_at'],
        ],
        'beneficiaires_session' => [['oev_id'], ['session_parrainage_id', 'statut', 'rang'], ['region_id']],
        'appuis_partenaires' => [['oev_id'], ['parrain_id']],
        'suivis_parrainage' => [['oev_id']],
        'journal_activites' => [['user_id']],
        'signalements' => [['region_id'], ['province_id']],
        'plaintes' => [['region_id'], ['province_id']],
    ];

    /** Index partiels (dossiers non supprimés) : nom => colonnes. Syntaxe commune à PostgreSQL et SQLite. */
    private const INDEX_PARTIELS = [
        'oevs_comptages_index' => ['statut_dossier', 'desactivation_etat', 'region_id', 'province_id', 'commune_id', 'statut', 'sexe', 'handicap', 'rejete_niveau'],
        'oevs_integration_index' => ['statut_dossier', 'integre_at', 'rejete_niveau'],
        'oevs_mise_a_jour_index' => ['updated_at'],
        // Tris de « Filtrage et extraction » : plus récents, nom, âge, localité
        'oevs_tri_recents_index' => ['created_at', 'id'],
        'oevs_tri_nom_index' => ['nom', 'prenom'],
        'oevs_tri_age_index' => ['date_naissance'],
        'oevs_tri_localite_index' => ['region_id', 'province_id', 'commune_id', 'nom'],
    ];

    public function up(): void
    {
        foreach (self::INDEX as $table => $liste) {
            Schema::table($table, function (Blueprint $t) use ($liste) {
                foreach ($liste as $colonnes) {
                    $t->index($colonnes);
                }
            });
        }
        foreach (self::INDEX_PARTIELS as $nom => $colonnes) {
            DB::statement("CREATE INDEX {$nom} ON oevs (" . implode(', ', $colonnes) . ') WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEX_PARTIELS) as $nom) {
            DB::statement("DROP INDEX {$nom}");
        }
        foreach (self::INDEX as $table => $liste) {
            Schema::table($table, function (Blueprint $t) use ($liste) {
                foreach ($liste as $colonnes) {
                    $t->dropIndex($colonnes);
                }
            });
        }
    }
};
