<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Parrains » :
 *  - natures_appui : table de référence (scolaire, santé, alimentaire…), complétable ;
 *  - parrain_zones : zone d'intervention d'un parrain (une région OU une province par ligne) ;
 *  - engagements_parrain : engagements pris par un parrain (durée, natures, nombre d'OEV, montant, convention) ;
 *  - appuis_partenaires : un parrain appuie un OEV pendant une année scolaire (RG-01, RG-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('natures_appui', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('libelle', 100);
            $table->boolean('scolaire')->default(false)->comment('Appui à la scolarité ou à la formation : la nature financée par les sessions de l’État');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        $natures = [
            ['scolaire', 'Scolaire', true],
            ['formation_professionnelle', 'Formation professionnelle', true],
            ['sante', 'Santé', false],
            ['alimentaire', 'Alimentaire', false],
            ['vestimentaire', 'Vestimentaire', false],
            ['materiel', 'Matériel', false],
            ['financier', 'Financier', false],
            ['autre', 'Autre', false],
        ];
        foreach ($natures as $ordre => [$code, $libelle, $scolaire]) {
            DB::table('natures_appui')->insert([
                'code' => $code, 'libelle' => $libelle, 'scolaire' => $scolaire, 'ordre' => $ordre + 1, 'actif' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('parrain_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parrain_id')->constrained('parrains')->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('engagements_parrain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parrain_id')->constrained('parrains')->cascadeOnDelete();
            $table->date('date_engagement');
            $table->unsignedSmallInteger('duree_mois')->nullable();
            $table->json('natures_appui')->nullable()->comment('Identifiants des natures d’appui proposées');
            $table->unsignedInteger('nombre_oev_prevu')->nullable();
            $table->unsignedBigInteger('montant_prevu')->nullable();
            $table->string('convention_chemin')->nullable();
            $table->string('convention_nom')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('appuis_partenaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oev_id')->constrained('oevs')->restrictOnDelete();
            $table->foreignId('parrain_id')->constrained('parrains')->restrictOnDelete();
            $table->string('annee', 9)->comment('Année scolaire, ex : 2026-2027');
            $table->foreignId('nature_appui_id')->constrained('natures_appui')->restrictOnDelete();
            $table->string('nature_precision', 150)->nullable()->comment('Précision pour la nature « Autre »');
            $table->unsignedBigInteger('montant')->nullable()->comment('FCFA ; vide pour un appui en nature');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->text('observations')->nullable();
            $table->text('motif_doublon')->nullable()->comment('Motif saisi pour confirmer un 2e appui de même nature la même année (RG-01)');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['annee', 'nature_appui_id', 'oev_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appuis_partenaires');
        Schema::dropIfExists('engagements_parrain');
        Schema::dropIfExists('parrain_zones');
        Schema::dropIfExists('natures_appui');
    }
};
