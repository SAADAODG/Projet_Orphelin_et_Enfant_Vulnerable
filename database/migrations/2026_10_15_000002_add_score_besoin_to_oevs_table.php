<?php

use App\Services\Parrainage\ScoreBesoin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score d'urgence du besoin enregistré sur la fiche (au lieu d'être recalculé sur chaque ligne à chaque
 * affichage de la sélection) : indexé pour le tri des éligibles. Tenu à jour par ScoreBesoinObserver ;
 * les dossiers existants sont calculés ici avec l'expression SQL du barème.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->unsignedSmallInteger('score_besoin')->default(0)->comment('Score d’urgence du besoin (0 à 100), voir ScoreBesoin');
            $table->index(['statut_dossier', 'score_besoin']);
        });

        ScoreBesoin::recalculerTout();
    }

    public function down(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->dropIndex(['statut_dossier', 'score_besoin']);
            $table->dropColumn('score_besoin');
        });
    }
};
