<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions de parrainage (module « Sessions ») :
 *  - sessions_parrainage : sessions financées par l'État ; enveloppe facultative (renseignable plus tard,
 *    jamais dépassée quand elle est saisie), plafond, options de sélection, état ;
 *  - quotas_regionaux : montant réservé à chaque région quand les quotas sont actifs.
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
            $table->unsignedBigInteger('enveloppe')->nullable()->comment('Facultative : sans enveloppe, le total des OEV retenus est affiché');
            $table->unsignedBigInteger('plafond_beneficiaire')->nullable()->comment('Facultatif : sans plafond, le montant retenu est égal aux frais réels');
            $table->unsignedBigInteger('estimation_scolarisation')->nullable()->comment('FCFA estimés par enfant d’âge scolaire non scolarisé');
            $table->unsignedBigInteger('estimation_formation')->nullable()->comment('FCFA estimés par OEV en formation professionnelle');
            $table->date('date_ouverture');
            $table->date('date_cloture')->nullable();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('quotas_regionaux');
        Schema::dropIfExists('sessions_parrainage');
    }
};
