<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Découpage administratif : Régions > Provinces > Communes > Villages / secteurs.
 * Créé en premier : utilisateurs, dossiers, signalements et plaintes y sont rattachés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('ancien_nom')->nullable()->comment('Ancienne appellation de la région (avant le découpage de 2025)');
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nom');
            $table->string('chef_lieu')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'nom']);
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nom');
            $table->timestamps();

            $table->unique(['province_id', 'nom']);
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nom');
            $table->timestamps();

            $table->unique(['commune_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
        Schema::dropIfExists('communes');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
    }
};
