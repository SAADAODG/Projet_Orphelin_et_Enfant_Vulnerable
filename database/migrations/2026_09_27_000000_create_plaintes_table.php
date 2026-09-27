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
        Schema::create('plaintes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Le problème signalé
            $table->string('objet');
            $table->text('description');
            $table->string('region')->nullable();
            $table->string('province')->nullable();
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plaintes');
    }
};
