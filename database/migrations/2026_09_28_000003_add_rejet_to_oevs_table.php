<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Rejet définitif d'un dossier, par le DR ou par le niveau central, avec motif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table): void {
            $table->text('motif_rejet')->nullable();
            $table->string('rejete_niveau', 10)->nullable();
            $table->timestamp('rejete_at')->nullable();
            $table->foreignId('rejete_par')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('oevs', 'rejete_par')) {
            Schema::table('oevs', fn (Blueprint $table) => $table->dropConstrainedForeignId('rejete_par'));
        }
        $colonnes = array_values(array_filter(['motif_rejet', 'rejete_niveau', 'rejete_at'], fn ($c) => Schema::hasColumn('oevs', $c)));
        if ($colonnes) {
            Schema::table('oevs', fn (Blueprint $table) => $table->dropColumn($colonnes));
        }
    }
};
