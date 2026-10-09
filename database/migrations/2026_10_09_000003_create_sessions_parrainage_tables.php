<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions de parrainage (module « Sessions ») :
 *  - sessions_parrainage : enveloppe, plafond, options RG-04 / RG-07, état ;
 *  - quotas_regionaux : montant réservé à chaque région quand les quotas sont actifs (RG-04) ;
 *  - contributions_session : parrains qui financent une session « Partenaire » ou « Mixte ».
 * Montants en FCFA, entiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_parrainage', function (Blueprint $table) {
            $table->id();
            $table->string('description', 255);
            $table->string('annee', 9)->comment('Année scolaire, ex : 2026-2027');
            $table->unsignedSmallInteger('numero');
            $table->string('type_appui', 30);
            $table->unsignedBigInteger('enveloppe');
            $table->unsignedBigInteger('plafond_beneficiaire');
            $table->string('source_financement', 20);
            $table->date('date_ouverture');
            $table->date('date_cloture')->nullable();
            $table->boolean('bloquer_depassement_enveloppe')->default(false);
            $table->boolean('quotas_actifs')->default(false);
            $table->boolean('exclure_deja_appuyes')->default(false);
            $table->string('etat', 20)->default('en_cours')->index();
            $table->foreignId('session_origine_id')->nullable()->comment('Session principale d’une session de rattrapage')
                ->constrained('sessions_parrainage')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['annee', 'numero']);
        });

        Schema::create('quotas_regionaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_parrainage_id')->constrained('sessions_parrainage')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->timestamps();

            $table->unique(['session_parrainage_id', 'region_id']);
        });

        Schema::create('contributions_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_parrainage_id')->constrained('sessions_parrainage')->cascadeOnDelete();
            $table->foreignId('parrain_id')->constrained('parrains')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->timestamps();

            $table->unique(['session_parrainage_id', 'parrain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions_session');
        Schema::dropIfExists('quotas_regionaux');
        Schema::dropIfExists('sessions_parrainage');
    }
};
