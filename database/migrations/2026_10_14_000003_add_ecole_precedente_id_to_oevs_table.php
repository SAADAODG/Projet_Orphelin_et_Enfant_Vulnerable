<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * École de l'année précédente choisie dans le référentiel (Paramètres > Écoles) ;
 * son nom est aussi gardé dans oevs.etablissement_precedent (établissement hors liste, exports).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->foreignId('ecole_precedente_id')->nullable()->after('etablissement_precedent')->constrained('ecoles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ecole_precedente_id');
        });
    }
};
