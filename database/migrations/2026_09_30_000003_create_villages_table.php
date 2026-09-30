<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quatrième niveau de localité : Région > Province > Commune > Village (ou secteur),
 * rattaché aux dossiers OEV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nom');
            $table->timestamps();

            $table->unique(['commune_id', 'nom']);
        });

        Schema::table('oevs', function (Blueprint $table) {
            $table->foreignId('village_id')->nullable()->after('commune_id')->constrained('villages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('oevs', 'village_id')) {
            Schema::table('oevs', fn (Blueprint $table) => $table->dropConstrainedForeignId('village_id'));
        }

        Schema::dropIfExists('villages');
    }
};
