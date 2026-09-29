<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table à ligne unique (singleton) : identité et coordonnées de la
     * plateforme, affichées sur le site public (en-tête, pied de page).
     */
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
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
