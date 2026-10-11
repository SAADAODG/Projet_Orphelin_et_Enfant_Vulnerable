<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Sélection » du parrainage : OEV choisis pour une session.
 *  - retenu : sur la liste de la session (liste définitive une fois la session validée) ;
 *  - attente : hors enveloppe ou hors quota, classé par rang.
 * Le score d'urgence, la région, les frais et le montant retenu sont figés au moment de l'ajout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaires_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_parrainage_id')->constrained('sessions_parrainage')->cascadeOnDelete();
            $table->foreignId('oev_id')->constrained('oevs')->restrictOnDelete();
            $table->string('type_appui', 30)->comment('scolaire ou formation_professionnelle');
            $table->string('statut', 10)->index()->comment('retenu ou attente');
            $table->unsignedInteger('rang')->nullable()->comment('Rang dans la liste d’attente');
            $table->unsignedTinyInteger('score')->default(0)->comment('Score d’urgence du besoin (0 à 100)');
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->unsignedBigInteger('frais_reels')->nullable()->comment('FCFA ; vide si non renseignés');
            $table->unsignedBigInteger('montant_retenu')->comment('min(frais réels, plafond) ; le plafond si les frais manquent');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_parrainage_id', 'oev_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaires_session');
    }
};
