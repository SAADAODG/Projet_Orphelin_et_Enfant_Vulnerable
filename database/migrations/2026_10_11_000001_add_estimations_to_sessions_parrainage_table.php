<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coût des OEV sans frais de scolarité connus (module « Sélection ») :
 *  - montant estimatif par session pour un enfant d'âge scolaire non scolarisé (à inscrire) et pour un OEV
 *    en formation professionnelle ;
 *  - sur chaque ligne de la sélection, frais_estimes indique que les frais viennent de ces estimations
 *    (ils suivent alors leurs modifications), et non de la fiche ou d'une saisie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions_parrainage', function (Blueprint $table) {
            $table->unsignedBigInteger('estimation_scolarisation')->nullable()->after('plafond_beneficiaire')
                ->comment('FCFA estimés par enfant d’âge scolaire non scolarisé');
            $table->unsignedBigInteger('estimation_formation')->nullable()->after('estimation_scolarisation')
                ->comment('FCFA estimés par OEV en formation professionnelle');
        });

        Schema::table('beneficiaires_session', function (Blueprint $table) {
            $table->boolean('frais_estimes')->default(false)->after('frais_reels');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaires_session', fn (Blueprint $table) => $table->dropColumn('frais_estimes'));
        Schema::table('sessions_parrainage', fn (Blueprint $table) => $table->dropColumn(['estimation_scolarisation', 'estimation_formation']));
    }
};
