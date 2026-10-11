<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plafond par bénéficiaire facultatif : sans plafond, le montant retenu est égal aux frais réels. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions_parrainage', function (Blueprint $table) {
            $table->unsignedBigInteger('plafond_beneficiaire')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sessions_parrainage', function (Blueprint $table) {
            $table->unsignedBigInteger('plafond_beneficiaire')->nullable(false)->change();
        });
    }
};
