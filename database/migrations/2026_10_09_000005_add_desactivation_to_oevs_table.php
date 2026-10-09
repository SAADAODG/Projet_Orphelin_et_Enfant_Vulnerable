<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Désactivation d'un OEV intégré (décès, majorité…) : il ne bénéficie plus d'aucune aide.
 * Le DP la demande avec un motif, le niveau central la valide (ou la refuse) et peut réactiver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->string('desactivation_etat', 20)->nullable()->index()->after('integre_par')
                ->comment('null : actif ; demandee : en attente du niveau central ; desactive');
            $table->string('desactivation_motif', 30)->nullable()->after('desactivation_etat');
            $table->text('desactivation_commentaire')->nullable()->after('desactivation_motif');
            $table->timestamp('desactivation_demandee_at')->nullable()->after('desactivation_commentaire');
            $table->foreignId('desactivation_demandee_par')->nullable()->after('desactivation_demandee_at')->constrained('users')->nullOnDelete();
            $table->timestamp('desactive_at')->nullable()->after('desactivation_demandee_par');
            $table->foreignId('desactive_par')->nullable()->after('desactive_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desactivation_demandee_par');
            $table->dropConstrainedForeignId('desactive_par');
            $table->dropColumn(['desactivation_etat', 'desactivation_motif', 'desactivation_commentaire', 'desactivation_demandee_at', 'desactive_at']);
        });
    }
};
