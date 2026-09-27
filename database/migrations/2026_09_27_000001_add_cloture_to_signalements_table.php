<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            // Agent qui a validé / rejeté le signalement
            $table->foreignId('traite_par')->nullable()->after('traite_le')->constrained('users')->nullOnDelete();

            // Clôture : le contact avec l'enfant a eu lieu, une décision de prise en charge est prise
            $table->date('date_visite')->nullable()->after('traite_par');
            $table->string('decision')->nullable()->after('date_visite');
            $table->text('compte_rendu')->nullable()->after('decision');
            $table->text('message_declarant')->nullable()->after('compte_rendu');
            $table->timestamp('cloture_le')->nullable()->after('message_declarant');
            $table->foreignId('cloture_par')->nullable()->after('cloture_le')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cloture_par');
            $table->dropConstrainedForeignId('traite_par');
            $table->dropColumn(['date_visite', 'decision', 'compte_rendu', 'message_declarant', 'cloture_le']);
        });
    }
};
