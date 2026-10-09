<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'activités commun à tous les modules : qui a fait quoi, quand, sur quel objet.
 * Alimenté par JournalActivite::consigner().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index()->comment('Ex : session_parrainage.creation');
            $table->nullableMorphs('sujet');
            $table->string('description', 500);
            $table->json('details')->nullable()->comment('Motif, filtres, valeurs modifiées, nombre de lignes…');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_activites');
    }
};
