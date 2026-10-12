<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche de l'enfant : pour un enfant ni scolarisé ni en formation professionnelle, son souhait
 * (aller à l'école, suivre une formation…), qui oriente un futur parrainage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->string('souhait_avenir', 20)->nullable()->after('frais_formation')->comment('ecole, formation, aucun ou ne_sait_pas');
            $table->string('souhait_avenir_precision', 150)->nullable()->after('souhait_avenir')->comment('Formation souhaitée');
        });
    }

    public function down(): void
    {
        Schema::table('oevs', fn (Blueprint $table) => $table->dropColumn(['souhait_avenir', 'souhait_avenir_precision']));
    }
};
