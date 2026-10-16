<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RIB de l'établissement fréquenté (saisi dans Scolarité > Année en cours), qui remplace
 * la pièce jointe « RIB de la structure ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->string('rib_etablissement', 60)->nullable()->after('ecole_id');
        });
    }

    public function down(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->dropColumn('rib_etablissement');
        });
    }
};
