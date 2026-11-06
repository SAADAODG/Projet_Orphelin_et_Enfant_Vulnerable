<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index des OEV bénéficiaires (Oev::beneficiaires()) : depuis l'âge de sortie paramétrable, ce filtre
 * porte aussi sur la date de naissance, absente de oevs_comptages_index. Sans elle, chaque indicateur du
 * pilotage relisait toute la table (mesuré sur 1 000 000 de dossiers, scripts/perf : 13 s → 4 s).
 * Zone (région, province, commune) et sexe inclus : comptages par zone sans lire la table (index-only scan).
 * Index partiel (dossiers non supprimés, comme toutes les requêtes) : syntaxe commune à PostgreSQL et SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX oevs_beneficiaires_index ON oevs (statut_dossier, region_id, province_id, commune_id, '
            . 'desactivation_etat, sexe, handicap, date_naissance, id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS oevs_beneficiaires_index');
    }
};
