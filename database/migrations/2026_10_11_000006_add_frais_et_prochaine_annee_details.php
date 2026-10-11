<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frais de l'année suivante demandés lors du suivi des parrainés :
 *  - oevs.frais_formation : frais de la formation professionnelle en cours (comme frais_scolarite pour l'école) ;
 *  - suivis_parrainage.details_prochaine_annee : frais, établissement, centre et durée saisis au suivi,
 *    reportés sur la fiche de l'OEV.
 * Le parcours scolaire et professionnel de l'OEV se reconstitue année par année à partir des suivis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->unsignedInteger('frais_formation')->nullable()->after('formation_duree_recue_mois')->comment('Frais de la formation professionnelle (FCFA)');
        });

        Schema::table('suivis_parrainage', function (Blueprint $table) {
            $table->json('details_prochaine_annee')->nullable()->after('prochaine_annee')
                ->comment('frais, etablissement, type_etablissement (école) ; frais, type_centre, duree_mois (formation)');
        });
    }

    public function down(): void
    {
        Schema::table('suivis_parrainage', fn (Blueprint $table) => $table->dropColumn('details_prochaine_annee'));
        Schema::table('oevs', fn (Blueprint $table) => $table->dropColumn('frais_formation'));
    }
};
