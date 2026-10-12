<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossiers enfants / OEV et leurs pièces jointes.
 *
 * Circuit : le DP constitue le dossier (numéro DOS-…), le DR le vérifie, le niveau central l'intègre
 * (code OEV-…), demande un complément ou le rejette. Un OEV intégré peut être désactivé (décès, majorité…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oevs', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_dossier')->nullable()->unique();
            $table->string('statut_dossier', 20)->default('brouillon')->index();
            $table->string('code')->nullable()->unique()->comment('Code OEV attribué à l’intégration');

            // Identité de l'enfant
            $table->string('nom', 100);
            $table->string('prenom', 150);
            $table->char('sexe', 1);
            $table->date('date_naissance');
            $table->boolean('date_naissance_estimee')->default(false);
            $table->string('lieu_naissance', 150)->nullable()->comment('Lieu hors référentiel ou hors du pays');
            $table->foreignId('lieu_naissance_commune_id')->nullable()->constrained('communes')->restrictOnDelete();
            $table->string('nationalite', 100)->nullable();
            $table->boolean('a_acte_naissance')->nullable();
            $table->string('numero_acte_naissance', 100)->nullable();
            $table->string('groupe_population', 30)->nullable();
            $table->string('statut', 40)->comment('Orphelin de père, de mère, double, ou enfant vulnérable');

            // Adresse et provenance
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->restrictOnDelete();
            $table->foreignId('village_id')->nullable()->constrained('villages')->restrictOnDelete();
            $table->string('quartier', 150)->nullable();
            $table->string('lieu_provenance', 150)->nullable()->comment('Lieu hors référentiel ou hors du pays');
            $table->foreignId('lieu_provenance_commune_id')->nullable()->constrained('communes')->restrictOnDelete();

            // Parents : vivant oui / non / ne_sait_pas
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

            // Tuteur et conditions de vie
            $table->string('nom_tuteur', 100);
            $table->string('prenom_tuteur', 150);
            $table->string('contact_tuteur', 30);
            $table->char('tuteur_sexe', 1)->nullable();
            $table->string('tuteur_lien', 100)->nullable();
            $table->string('tuteur_lien_precision', 100)->nullable();
            $table->boolean('tuteur_a_cnib')->nullable();
            $table->string('tuteur_cnib', 50)->nullable();
            $table->boolean('tuteur_pret_continuer')->nullable();
            $table->string('tuteur_raison_arret', 255)->nullable();
            $table->string('lieu_de_vie', 30)->nullable();
            $table->string('lieu_de_vie_precision', 150)->nullable();

            // Vulnérabilités (liste de codes) et santé
            $table->json('vulnerabilites')->nullable();
            $table->string('vulnerabilite_precision', 255)->nullable();
            $table->boolean('handicap')->default(false);
            $table->string('nature_handicap')->nullable();
            $table->json('types_handicap')->nullable();
            $table->boolean('maladie_chronique')->nullable();
            $table->string('maladie_nom', 255)->nullable();
            $table->boolean('suivi_clinique')->nullable();

            // Scolarité : un enfant non scolarisé n'a ni établissement, ni classe, ni frais
            $table->string('situation_scolaire', 30)->nullable();
            $table->string('niveau_etude', 30)->nullable();
            $table->string('systeme_educatif', 40)->nullable();
            $table->string('raison_non_scolarisation', 30)->nullable();
            $table->string('raison_non_scolarisation_precision', 255)->nullable();
            $table->string('etablissement_precedent')->nullable();
            $table->string('classe_precedente', 20)->nullable();
            $table->decimal('moyenne_annuelle', 4, 2)->nullable();
            $table->string('appreciation', 20)->nullable();
            $table->string('performance_scolaire', 20)->nullable();
            $table->string('performance_difficultes', 255)->nullable();
            $table->string('etablissement_actuel')->nullable();
            $table->string('type_etablissement', 10)->nullable();
            $table->string('classe', 50)->nullable();
            $table->unsignedInteger('frais_scolarite')->nullable();
            $table->string('nom_structure_rib')->nullable()->comment('Structure bénéficiaire du RIB');

            // Formation professionnelle
            $table->boolean('formation_professionnelle')->nullable();
            $table->string('formation_etat', 15)->nullable();
            $table->string('formation_filiere', 150)->nullable();
            $table->string('formation_type_centre', 10)->nullable();
            $table->unsignedSmallInteger('formation_duree_mois')->nullable();
            $table->unsignedSmallInteger('formation_duree_recue_mois')->nullable();
            $table->unsignedInteger('frais_formation')->nullable()->comment('Frais de la formation professionnelle (FCFA)');

            // Ménage
            $table->string('source_revenu', 30)->nullable();
            $table->string('niveau_revenu', 10)->nullable();
            $table->string('logement', 30)->nullable();

            // Identification du cas et gestionnaire
            $table->date('date_identification')->nullable();
            $table->string('identifie_par', 30)->nullable();
            $table->string('niveau_priorite', 10)->nullable();
            $table->string('gestionnaire_nom', 150)->nullable();
            $table->string('gestionnaire_fonction', 150)->nullable();
            $table->string('gestionnaire_contact', 20)->nullable();

            // Circuit du dossier
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('soumis_at')->nullable();
            $table->foreignId('soumis_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verifie_at')->nullable();
            $table->foreignId('verifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_non_conformite')->nullable();
            $table->timestamp('integre_at')->nullable();
            $table->foreignId('integre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_complement')->nullable();
            $table->timestamp('complement_at')->nullable();
            $table->foreignId('complement_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_rejet')->nullable();
            $table->string('rejete_niveau', 10)->nullable();
            $table->timestamp('rejete_at')->nullable();
            $table->foreignId('rejete_par')->nullable()->constrained('users')->nullOnDelete();

            // Désactivation d'un OEV intégré : demandée par le DP, validée par le niveau central
            $table->string('desactivation_etat', 20)->nullable()->index()
                ->comment('null : actif ; demandee : en attente du niveau central ; desactive');
            $table->string('desactivation_motif', 30)->nullable();
            $table->text('desactivation_commentaire')->nullable();
            $table->timestamp('desactivation_demandee_at')->nullable();
            $table->foreignId('desactivation_demandee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('desactive_at')->nullable();
            $table->foreignId('desactive_par')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('oev_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('oev_id')->constrained('oevs')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('chemin');
            $table->string('nom_original');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('taille')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['oev_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oev_documents');
        Schema::dropIfExists('oevs');
    }
};
