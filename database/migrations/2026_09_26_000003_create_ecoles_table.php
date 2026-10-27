<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des établissements (Paramètres > Écoles et centres) : écoles, rattachées à leur commune et à
 * leur CEB, et centres de formation professionnelle. L'OEV scolarisé choisit son école dans celles de sa
 * commune (oevs.ecole_id ; nom gardé dans oevs.etablissement_actuel), l'OEV en formation son centre
 * (oevs.centre_formation_id). La banque et le RIB de l'établissement servent au paiement des parrainages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecoles', function (Blueprint $table) {
            $table->id();
            $table->string('nature', 20)->default('ecole')->comment('ecole ou centre_formation');
            $table->foreignId('commune_id')->constrained('communes')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('ceb', 150)->nullable()->comment('Circonscription d’éducation de base');
            $table->string('nom');
            $table->string('statut', 20)->nullable()->comment('public, prive ou communautaire');
            $table->string('type', 30)->nullable()->comment('classique, franco_arabe, bilingue…');
            $table->string('banque', 120)->nullable();
            $table->string('rib', 60)->nullable()->comment('Relevé d’identité bancaire de l’établissement');
            $table->timestamps();

            $table->index(['commune_id', 'nom']);
            $table->index(['nature', 'commune_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecoles');
    }
};
