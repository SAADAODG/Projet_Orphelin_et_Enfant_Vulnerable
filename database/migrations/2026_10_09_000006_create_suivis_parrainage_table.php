<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des parrainés : un suivi de fin d'année par OEV retenu sur la liste définitive d'une session
 * (résultat scolaire ou de formation), qui éclaire les parrainages suivants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suivis_parrainage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiaire_session_id')->unique()->constrained('beneficiaires_session')->cascadeOnDelete();
            $table->foreignId('oev_id')->index()->constrained('oevs')->restrictOnDelete();
            $table->string('annee', 9)->index()->comment('Année scolaire de la session, ex. 2026-2027');
            $table->string('type_appui', 30)->comment('scolaire ou formation_professionnelle');
            $table->string('resultat', 20)->comment('Scolaire : admis, redouble, abandon. Formation : poursuit, achevee, abandon');
            $table->decimal('moyenne', 5, 2)->nullable()->comment('Moyenne annuelle (scolaire), sur 10 ou 20 selon la classe');
            $table->string('insertion', 20)->nullable()->comment('Formation achevée : emploi, auto_emploi, recherche');
            $table->string('orientation', 20)->nullable()->comment('ecole, formation ou arret');
            $table->string('prochaine_annee', 150)->nullable()
                ->comment('Scolaire : classe prévue (code, ex. 4e). Formation : formation prévue (texte libre)');
            $table->json('details_prochaine_annee')->nullable()
                ->comment('frais, etablissement, type_etablissement (école) ; frais, type_centre, duree_mois (formation)');
            $table->boolean('a_reconduire')->comment('Recommandé pour un nouveau parrainage');
            $table->text('observation')->nullable();
            $table->json('fiche_avant')->nullable()->comment('Scolarité / formation de la fiche avant le suivi');
            $table->foreignId('renseigne_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suivis_parrainage');
    }
};
