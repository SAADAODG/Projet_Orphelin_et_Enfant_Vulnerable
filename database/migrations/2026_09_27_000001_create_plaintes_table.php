<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plaintes et avis des usagers (contribution, mécontentement, reconnaissance, appréciation). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plaintes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            // Le problème signalé
            $table->string('objet');
            $table->text('description');
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->restrictOnDelete();
            $table->string('localite')->nullable();
            $table->string('recepisse_signalement')->nullable();
            // Contact facultatif : la personne peut rester anonyme
            $table->boolean('anonyme')->default(true);
            $table->string('nom')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            // Traitement par l'agent
            $table->string('statut')->default('nouvelle')->index();
            $table->timestamp('lu_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plaintes');
    }
};
