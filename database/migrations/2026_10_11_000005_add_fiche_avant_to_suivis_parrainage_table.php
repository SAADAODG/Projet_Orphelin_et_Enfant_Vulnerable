<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le suivi met à jour la fiche de l'OEV (scolarité, formation). La scolarité de la fiche avant le premier
 * enregistrement est conservée : corriger le suivi repart de cet état (pas de double passage de classe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suivis_parrainage', function (Blueprint $table) {
            $table->json('fiche_avant')->nullable()->after('observation')->comment('Scolarité / formation de la fiche avant le suivi');
        });
    }

    public function down(): void
    {
        Schema::table('suivis_parrainage', fn (Blueprint $table) => $table->dropColumn('fiche_avant'));
    }
};
