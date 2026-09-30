<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement géographique des comptes : le DR à sa région, le DP à sa province
 * (la région est alors celle de la province). Le niveau central n'est rattaché à rien.
 *
 * Supprimer une localité ne supprime pas le compte : le rattachement est simplement retiré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('active')->constrained('regions')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->after('region_id')->constrained('provinces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('province_id');
            $table->dropConstrainedForeignId('region_id');
        });
    }
};
