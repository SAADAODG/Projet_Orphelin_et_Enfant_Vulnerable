<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Signalements d'enfants reçus du public, traités par le DP puis clôturés après la visite. */
return new class extends Migration
{
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
            // Localité
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->restrictOnDelete();
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
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            // Clôture : le contact avec l'enfant a eu lieu, une décision de prise en charge est prise
            $table->date('date_visite')->nullable();
            $table->string('decision')->nullable();
            $table->text('compte_rendu')->nullable();
            $table->text('message_declarant')->nullable();
            $table->timestamp('cloture_le')->nullable();
            $table->foreignId('cloture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lu_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};
