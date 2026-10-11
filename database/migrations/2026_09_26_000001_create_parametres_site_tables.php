<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paramètres généraux du site : identité, liens rapides du pied de page, services présentés. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('nom_site');
            $table->string('slogan')->nullable();
            $table->string('structure_nom');
            $table->string('structure_nom_complet')->nullable();
            $table->text('structure_description')->nullable();
            // Affiché notamment dans l'en-tête du site public.
            $table->string('ministere_tutelle')->nullable();
            // Police de caractères de l'interface admin et du site public, réglables
            // indépendamment depuis Paramètres > Apparence — voir config/fonts.php pour la
            // liste vétée des valeurs acceptées.
            $table->string('police_admin')->default('systeme');
            $table->string('police_public')->default('inter');
            $table->string('contact_adresse')->nullable();
            $table->string('contact_telephone')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });

        Schema::create('quick_links', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('url');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('url')->default('#');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('quick_links');
        Schema::dropIfExists('site_settings');
    }
};
