<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Circuit du dossier enfant : DP (constitution) → DR (vérification) → niveau central (intégration).
 * L'enfant ne reçoit son code OEV qu'à l'intégration ; avant, le dossier porte un numéro de dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table): void {
            $table->string('numero_dossier')->nullable()->after('id');
            $table->string('statut_dossier', 20)->default('brouillon')->index()->after('numero_dossier');

            $table->timestamp('soumis_at')->nullable();
            $table->foreignId('soumis_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verifie_at')->nullable();
            $table->foreignId('verifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_non_conformite')->nullable();
            $table->timestamp('integre_at')->nullable();
            $table->foreignId('integre_par')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('oevs', function (Blueprint $table): void {
            $table->string('code')->nullable()->change();
        });

        // Dossiers existants : le code OEV devient le numéro de dossier, et ils repartent au stade « brouillon ».
        foreach (DB::table('oevs')->orderBy('id')->get(['id', 'code']) as $ligne) {
            DB::table('oevs')->where('id', $ligne->id)->update([
                'numero_dossier' => $ligne->code ? preg_replace('/^OEV-/', 'DOS-', $ligne->code) : sprintf('DOS-%s-%04d', now()->year, $ligne->id),
                'code' => null,
            ]);
        }

        Schema::table('oevs', function (Blueprint $table): void {
            $table->unique('numero_dossier');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('oevs', 'numero_dossier')) {
            foreach (DB::table('oevs')->whereNull('code')->get(['id', 'numero_dossier']) as $ligne) {
                DB::table('oevs')->where('id', $ligne->id)->update(['code' => preg_replace('/^DOS-/', 'OEV-', (string) $ligne->numero_dossier)]);
            }
        }

        // SQLite : les index doivent être supprimés avant les colonnes qu'ils couvrent.
        $index = collect(Schema::getIndexes('oevs'))->pluck('name');
        Schema::table('oevs', function (Blueprint $table) use ($index): void {
            if ($index->contains('oevs_numero_dossier_unique')) {
                $table->dropUnique('oevs_numero_dossier_unique');
            }
            if ($index->contains('oevs_statut_dossier_index')) {
                $table->dropIndex('oevs_statut_dossier_index');
            }
        });

        foreach (['soumis_par', 'verifie_par', 'integre_par'] as $cle) {
            if (Schema::hasColumn('oevs', $cle)) {
                Schema::table('oevs', fn (Blueprint $table) => $table->dropConstrainedForeignId($cle));
            }
        }

        $colonnes = array_values(array_filter(
            ['numero_dossier', 'statut_dossier', 'soumis_at', 'verifie_at', 'motif_non_conformite', 'integre_at'],
            fn ($c) => Schema::hasColumn('oevs', $c),
        ));
        if ($colonnes) {
            Schema::table('oevs', fn (Blueprint $table) => $table->dropColumn($colonnes));
        }

        Schema::table('oevs', function (Blueprint $table): void {
            $table->string('code')->nullable(false)->change();
        });
    }
};
