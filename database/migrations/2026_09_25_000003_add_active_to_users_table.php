<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('active')->default(true)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('active');
            });
        }
    }
};