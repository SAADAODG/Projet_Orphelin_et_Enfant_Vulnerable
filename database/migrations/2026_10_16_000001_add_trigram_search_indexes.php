<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recherche libre (« contient le texte », sans tenir compte de la casse) servie par des index trigrammes
 * (extension PostgreSQL pg_trgm, livrée avec PostgreSQL) au lieu d'un parcours de tous les dossiers.
 *
 * PostgreSQL seulement : sous SQLite (tests), la migration ne fait rien. L'activation de l'extension demande
 * le droit CREATE sur la base (souvent un compte administrateur) : voir docs/performance.md.
 */
return new class extends Migration
{
    /** Colonnes cherchées par les listes, le filtrage et la sélection (dossiers non supprimés). */
    private const COLONNES = [
        'code', 'numero_dossier', 'nom', 'prenom', 'nom_tuteur', 'prenom_tuteur',
        'mere_nom', 'pere_nom', 'etablissement_actuel', 'quartier',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        foreach (self::COLONNES as $colonne) {
            DB::statement("CREATE INDEX IF NOT EXISTS oevs_{$colonne}_trgm_index ON oevs USING gin ({$colonne} gin_trgm_ops) WHERE deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLONNES as $colonne) {
            DB::statement("DROP INDEX IF EXISTS oevs_{$colonne}_trgm_index");
        }
        // L'extension est laissée : d'autres objets de la base peuvent s'en servir
    }
};
