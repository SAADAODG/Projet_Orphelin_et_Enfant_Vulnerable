<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement d'une session de parrainage : fonds reçus par un établissement (école ou centre de formation),
 * avec le montant et le nombre d'OEV du moment, la date de réception et la référence du virement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_etablissement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_parrainage_id')->constrained('sessions_parrainage')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('ecoles')->restrictOnDelete();
            $table->unsignedBigInteger('montant')->comment('Montant versé (FCFA) : total des OEV de l’établissement au moment du paiement');
            $table->unsignedInteger('nombre_oev');
            $table->date('recu_le')->comment('Date de réception des fonds par l’établissement');
            $table->string('reference', 100)->nullable()->comment('Référence du virement');
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_parrainage_id', 'etablissement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_etablissement');
    }
};
