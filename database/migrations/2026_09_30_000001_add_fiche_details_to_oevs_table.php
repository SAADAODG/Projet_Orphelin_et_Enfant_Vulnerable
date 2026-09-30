<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Champs repris de la fiche d'identification / d'évaluation des gestionnaires de cas,
 * limités à ce qui concerne les OEV (identité, parents, conditions de vie, vulnérabilités,
 * santé, ménage, scolarité, identification du cas).
 */
return new class extends Migration
{
    /** Colonnes ajoutées, pour le retour arrière. */
    private const COLONNES = [
        'date_naissance_estimee', 'lieu_naissance', 'nationalite', 'a_acte_naissance', 'numero_identification', 'groupe_population',
        'quartier', 'lieu_provenance',
        'mere_nom', 'mere_prenoms', 'mere_vivante', 'mere_date_deces', 'mere_deces_confirme',
        'pere_nom', 'pere_prenoms', 'pere_vivant', 'pere_date_deces', 'pere_deces_confirme',
        'lieu_de_vie', 'lieu_de_vie_precision',
        'tuteur_sexe', 'tuteur_lien', 'tuteur_cnib', 'tuteur_pret_continuer',
        'vulnerabilites', 'vulnerabilite_precision',
        'situation_scolaire', 'niveau_etude', 'raison_non_scolarisation', 'raison_non_scolarisation_precision',
        'formation_professionnelle', 'formation_etat', 'formation_filiere', 'formation_type_centre',
        'types_handicap', 'maladie_chronique', 'maladie_details',
        'source_revenu', 'niveau_revenu', 'logement',
        'date_identification', 'identifie_par', 'niveau_priorite',
    ];

    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table): void {
            // Identité
            $table->boolean('date_naissance_estimee')->default(false);
            $table->string('lieu_naissance', 150)->nullable();
            $table->string('nationalite', 100)->nullable();
            $table->boolean('a_acte_naissance')->nullable();
            $table->string('numero_identification', 100)->nullable();
            $table->string('groupe_population', 30)->nullable();

            // Adresse
            $table->string('quartier', 150)->nullable();
            $table->string('lieu_provenance', 150)->nullable();

            // Parents : oui / non / ne_sait_pas
            $table->string('mere_nom', 100)->nullable();
            $table->string('mere_prenoms', 150)->nullable();
            $table->string('mere_vivante', 12)->nullable();
            $table->date('mere_date_deces')->nullable();
            $table->boolean('mere_deces_confirme')->nullable();
            $table->string('pere_nom', 100)->nullable();
            $table->string('pere_prenoms', 150)->nullable();
            $table->string('pere_vivant', 12)->nullable();
            $table->date('pere_date_deces')->nullable();
            $table->boolean('pere_deces_confirme')->nullable();

            // Conditions de vie et tuteur
            $table->string('lieu_de_vie', 30)->nullable();
            $table->string('lieu_de_vie_precision', 150)->nullable();
            $table->char('tuteur_sexe', 1)->nullable();
            $table->string('tuteur_lien', 100)->nullable();
            $table->string('tuteur_cnib', 50)->nullable();
            $table->boolean('tuteur_pret_continuer')->nullable();

            // Vulnérabilités (liste de codes)
            $table->json('vulnerabilites')->nullable();
            $table->string('vulnerabilite_precision', 255)->nullable();

            // Scolarité et formation
            $table->string('situation_scolaire', 30)->nullable();
            $table->string('niveau_etude', 30)->nullable();
            $table->string('raison_non_scolarisation', 30)->nullable();
            $table->string('raison_non_scolarisation_precision', 255)->nullable();
            $table->boolean('formation_professionnelle')->nullable();
            $table->string('formation_etat', 15)->nullable();
            $table->string('formation_filiere', 150)->nullable();
            $table->string('formation_type_centre', 10)->nullable();

            // Santé
            $table->json('types_handicap')->nullable();
            $table->boolean('maladie_chronique')->nullable();
            $table->string('maladie_details', 255)->nullable();

            // Ménage
            $table->string('source_revenu', 30)->nullable();
            $table->string('niveau_revenu', 10)->nullable();
            $table->string('logement', 30)->nullable();

            // Identification du cas
            $table->date('date_identification')->nullable();
            $table->string('identifie_par', 30)->nullable();
            $table->string('niveau_priorite', 10)->nullable();
        });

        // Un enfant non scolarisé n'a ni établissement, ni classe, ni frais : ces colonnes deviennent facultatives.
        Schema::table('oevs', function (Blueprint $table): void {
            $table->string('systeme_educatif', 40)->nullable()->change();
            $table->string('etablissement_actuel')->nullable()->change();
            $table->string('type_etablissement', 10)->nullable()->change();
            $table->string('classe', 50)->nullable()->change();
            $table->unsignedInteger('frais_scolarite')->nullable()->default(null)->change();
        });

        // Les dossiers existants étaient tous « scolarisés » (les champs de scolarité étaient obligatoires).
        DB::table('oevs')->whereNull('situation_scolaire')->update(['situation_scolaire' => 'scolarise']);
    }

    public function down(): void
    {
        // Les colonnes de scolarité restent facultatives : les rendre à nouveau obligatoires
        // échouerait pour les enfants non scolarisés déjà enregistrés.
        $colonnes = array_values(array_filter(self::COLONNES, fn ($c) => Schema::hasColumn('oevs', $c)));
        if ($colonnes) {
            Schema::table('oevs', fn (Blueprint $table) => $table->dropColumn($colonnes));
        }
    }
};
