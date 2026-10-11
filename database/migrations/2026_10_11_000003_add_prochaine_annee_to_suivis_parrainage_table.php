<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des parrainés : scolarité ou formation prévue l'année suivante (s'il y a lieu),
 * pour préparer un nouveau parrainage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suivis_parrainage', function (Blueprint $table) {
            $table->string('prochaine_annee', 150)->nullable()->after('insertion')
                ->comment('Scolaire : classe prévue (code, ex. 4e). Formation : formation prévue (texte libre)');
        });
    }

    public function down(): void
    {
        Schema::table('suivis_parrainage', fn (Blueprint $table) => $table->dropColumn('prochaine_annee'));
    }
};
