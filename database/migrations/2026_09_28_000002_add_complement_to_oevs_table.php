<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Demande de complément du niveau central : le dossier validé revient au DR,
 * qui le complète lui-même ou le renvoie au DP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table): void {
            $table->text('motif_complement')->nullable();
            $table->timestamp('complement_at')->nullable();
            $table->foreignId('complement_par')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('oevs', 'complement_par')) {
            Schema::table('oevs', fn (Blueprint $table) => $table->dropConstrainedForeignId('complement_par'));
        }
        $colonnes = array_values(array_filter(['motif_complement', 'complement_at'], fn ($c) => Schema::hasColumn('oevs', $c)));
        if ($colonnes) {
            Schema::table('oevs', fn (Blueprint $table) => $table->dropColumn($colonnes));
        }
    }
};
