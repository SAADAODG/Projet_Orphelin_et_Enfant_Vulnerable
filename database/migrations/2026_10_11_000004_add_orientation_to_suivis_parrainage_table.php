<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des parrainés : orientation de l'année suivante, distincte du résultat
 * (un enfant admis peut quitter l'école pour une formation professionnelle).
 *  - ecole : continue l'école, prochaine_annee = classe calculée (suivante si admis, même classe sinon) ;
 *  - formation : formation professionnelle, prochaine_annee = filière ;
 *  - arret : ni école ni formation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suivis_parrainage', function (Blueprint $table) {
            $table->string('orientation', 20)->nullable()->after('insertion')->comment('ecole, formation ou arret');
        });
    }

    public function down(): void
    {
        Schema::table('suivis_parrainage', fn (Blueprint $table) => $table->dropColumn('orientation'));
    }
};
