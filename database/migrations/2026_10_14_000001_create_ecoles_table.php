<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des écoles (Paramètres > Écoles), rattachées à leur commune et à leur CEB.
 * L'OEV scolarisé choisit son établissement dans les écoles de sa commune (oevs.ecole_id) ;
 * son nom est aussi gardé dans oevs.etablissement_actuel (établissement hors liste, recherches, exports).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecoles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('ceb', 150)->nullable()->comment('Circonscription d’éducation de base');
            $table->string('nom');
            $table->string('statut', 20)->nullable()->comment('public, prive ou communautaire');
            $table->string('type', 30)->nullable()->comment('classique, franco_arabe, bilingue…');
            $table->timestamps();

            $table->index(['commune_id', 'nom']);
        });

        Schema::table('oevs', function (Blueprint $table) {
            $table->foreignId('ecole_id')->nullable()->after('etablissement_actuel')->constrained('ecoles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ecole_id');
        });
        Schema::dropIfExists('ecoles');
    }
};
