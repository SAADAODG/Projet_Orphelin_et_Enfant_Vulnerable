<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Répertoire des parrains (ONG, entreprises, particuliers…) qui financent des sessions
 * ou appuient directement des OEV. Un parrain qui a des appuis se désactive, il ne se supprime pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parrains', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('nom', 200);
            $table->string('contact_nom', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->boolean('actif')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parrains');
    }
};
