<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->string('recepisse')->unique();

            // Enfant signalé
            $table->string('enfant_nom');
            $table->string('enfant_prenom');
            $table->unsignedTinyInteger('enfant_age');
            $table->string('vulnerabilite');
            $table->string('vulnerabilite_precision')->nullable();
            $table->string('region');
            $table->string('province');
            $table->string('localite');

            // Personne qui signale
            $table->string('declarant_nom');
            $table->string('declarant_prenom');
            $table->string('declarant_telephone');
            $table->string('declarant_adresse');
            $table->string('declarant_profession');
            $table->string('declarant_lien');
            $table->string('declarant_lien_precision')->nullable();

            // Traitement par l'agent
            $table->string('statut')->default('en_attente')->index();
            $table->text('motif_rejet')->nullable();
            $table->timestamp('traite_le')->nullable();
            $table->timestamp('lu_at')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};
