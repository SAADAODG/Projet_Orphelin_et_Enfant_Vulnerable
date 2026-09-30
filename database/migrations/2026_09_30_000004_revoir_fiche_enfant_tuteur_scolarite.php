<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Révision de la fiche OEV :
 *  - enfant : n° d'acte de naissance (au lieu d'un n° CNIB) ; lieu de naissance choisi parmi les communes ;
 *  - tuteur : raison si le tuteur n'est pas prêt à continuer ;
 *  - santé : nom de la maladie et suivi clinique ;
 *  - scolarité : classe de l'année précédente (barème /10 ou /20), performances scolaires ;
 *  - formation professionnelle : durée prévue et durée déjà reçue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->renameColumn('numero_identification', 'numero_acte_naissance');
            $table->renameColumn('maladie_details', 'maladie_nom');
        });

        Schema::table('oevs', function (Blueprint $table) {
            $table->foreignId('lieu_naissance_commune_id')->nullable()->after('lieu_naissance')->constrained('communes')->restrictOnDelete();
            $table->string('tuteur_raison_arret', 255)->nullable()->after('tuteur_pret_continuer');
            $table->boolean('suivi_clinique')->nullable()->after('maladie_nom');
            $table->string('classe_precedente', 20)->nullable()->after('etablissement_precedent');
            $table->string('performance_scolaire', 20)->nullable()->after('appreciation');
            $table->string('performance_difficultes', 255)->nullable()->after('performance_scolaire');
            $table->unsignedSmallInteger('formation_duree_mois')->nullable()->after('formation_type_centre');
            $table->unsignedSmallInteger('formation_duree_recue_mois')->nullable()->after('formation_duree_mois');
        });

        // Lieu de naissance saisi en texte : rattaché à la commune du même nom quand elle existe
        $communes = [];
        foreach (DB::table('communes')->get(['id', 'nom']) as $commune) {
            $communes[$this->cle($commune->nom)] ??= $commune->id;
        }
        foreach (DB::table('oevs')->whereNotNull('lieu_naissance')->get(['id', 'lieu_naissance']) as $ligne) {
            if ($id = $communes[$this->cle($ligne->lieu_naissance)] ?? null) {
                DB::table('oevs')->where('id', $ligne->id)->update(['lieu_naissance_commune_id' => $id, 'lieu_naissance' => null]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lieu_naissance_commune_id');
            $table->dropColumn([
                'tuteur_raison_arret', 'suivi_clinique', 'classe_precedente', 'performance_scolaire',
                'performance_difficultes', 'formation_duree_mois', 'formation_duree_recue_mois',
            ]);
        });

        Schema::table('oevs', function (Blueprint $table) {
            $table->renameColumn('numero_acte_naissance', 'numero_identification');
            $table->renameColumn('maladie_nom', 'maladie_details');
        });
    }

    private function cle(?string $nom): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $nom)));
    }
};
