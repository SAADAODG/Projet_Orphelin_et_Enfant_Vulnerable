<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres du module OEV (Paramètres > Âge des OEV) : âge jusqu'auquel un enfant a le statut d'OEV,
 * plus long pour un enfant en situation de handicap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_oev', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 60)->unique();
            $table->string('valeur', 255)->nullable();
            $table->timestamps();
        });

        DB::table('parametres_oev')->insert([
            ['cle' => 'age_limite', 'valeur' => '18', 'created_at' => now(), 'updated_at' => now()],
            ['cle' => 'age_limite_handicap', 'valeur' => '25', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_oev');
    }
};
