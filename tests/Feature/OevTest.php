<?php

namespace Tests\Feature;

use App\Models\Oev;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OevTest extends TestCase
{
    use RefreshDatabase;

    /** Circuit : DP (constitue) → DR (vérifie) → niveau central (intègre). */
    private User $dp;
    private User $dr;
    private User $central;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->dp = User::factory()->create()->assignRole('DP');
        $this->dr = User::factory()->create()->assignRole('DR');
        $this->central = User::factory()->create()->assignRole('agent DGFE');
    }

    private function donneesOev(array $surcharge = []): array
    {
        return $surcharge + [
            'nom' => 'OUEDRAOGO',
            'prenom' => 'Awa',
            'sexe' => 'F',
            'date_naissance' => now()->subYears(11)->subMonths(2)->toDateString(),
            // Père décédé, mère vivante → statut « orphelin de père » calculé automatiquement
            'mere_vivante' => 'oui',
            'pere_vivant' => 'non',
            'a_acte_naissance' => '1',
            'tuteur_lien' => 'oncle_tante',
            'tuteur_a_cnib' => '1',
            'tuteur_cnib' => 'B1234567',
            'handicap' => '0',
            'situation_scolaire' => 'scolarise',
            'niveau_etude' => 'post_primaire_secondaire',
            'systeme_educatif' => 'classique',
            'nom_tuteur' => 'SAWADOGO',
            'prenom_tuteur' => 'Issa',
            'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_precedent' => 'École A de Koulouba',
            'moyenne_annuelle' => '14.5',
            'appreciation' => 'admis',
            'etablissement_actuel' => 'Lycée Philippe Zinda Kaboré',
            'type_etablissement' => 'public',
            'classe' => '6e',
            'frais_scolarite' => 25000,
            'region' => 'Centre',
            'province' => 'Kadiogo',
            'commune' => 'Ouagadougou',
        ];
    }

    private function pieces(): array
    {
        return [
            'acte_naissance' => UploadedFile::fake()->create('acte.pdf', 100, 'application/pdf'),
            'certificat_scolarite' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
            'photo' => UploadedFile::fake()->image('photo.jpg'),
            'cnib_tuteur' => UploadedFile::fake()->create('cnib.pdf', 100, 'application/pdf'),
            'rib' => UploadedFile::fake()->create('rib.pdf', 100, 'application/pdf'),
        ];
    }

    /** Dossier complet constitué par le DP (état « brouillon »). */
    private function dossierComplet(array $surcharge = []): Oev
    {
        $this->actingAs($this->dp)
            ->post(route('oevs.store'), $this->donneesOev($surcharge + ['nom_structure_rib' => 'Association Espoir']) + $this->pieces())
            ->assertSessionHasNoErrors();

        return Oev::latest('id')->firstOrFail();
    }

    // ---------- Constitution (DP) ----------

    public function test_le_dp_constitue_un_dossier_qui_n_est_pas_encore_un_oev(): void
    {
        $this->actingAs($this->dp)->get(route('oevs.create'))->assertOk();
        $this->post(route('oevs.store'), $this->donneesOev())->assertSessionHasNoErrors()->assertRedirect();

        $oev = Oev::firstOrFail();
        $this->assertSame('DOS-' . now()->year . '-0001', $oev->numero_dossier);
        $this->assertNull($oev->code, 'Le code OEV n’est attribué qu’à l’intégration');
        $this->assertSame(Oev::ETAT_BROUILLON, $oev->statut_dossier);
        $this->assertSame('OUEDRAOGO', $oev->nom);
        $this->assertSame('Issa', $oev->prenom_tuteur);
        $this->assertSame(11, $oev->age());
        $this->get(route('oevs.show', $oev))->assertOk()->assertSeeInOrder(['OUEDRAOGO', 'Awa', $oev->numero_dossier]);
    }

    public function test_les_pieces_sont_stockees_et_consultables(): void
    {
        $oev = $this->dossierComplet();

        $this->assertTrue($oev->estComplet());
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disque */
        $disque = Storage::disk('local');
        foreach ($oev->documents as $document) {
            $disque->assertExists($document->chemin);
        }
        $this->get(route('oevs.documents.show', [$oev, $oev->documents->first()]))->assertOk();
    }

    public function test_le_dp_peut_completer_le_dossier_plus_tard(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev());
        $oev = Oev::firstOrFail();

        $this->get(route('oevs.edit', $oev))->assertOk();
        $this->put(route('oevs.update', $oev), $this->donneesOev(['classe' => '5e', 'nom_structure_rib' => 'ONG Avenir']) + $this->pieces())
            ->assertSessionHasNoErrors();

        $oev->refresh();
        $this->assertSame('5e', $oev->classe);
        $this->assertTrue($oev->estComplet());
    }

    // ---------- Validation du formulaire ----------

    public function test_le_rib_exige_le_nom_de_la_structure(): void
    {
        $this->actingAs($this->dp)
            ->post(route('oevs.store'), $this->donneesOev() + ['rib' => UploadedFile::fake()->create('rib.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('nom_structure_rib');
    }

    public function test_le_type_de_handicap_est_exige_si_handicap(): void
    {
        $this->actingAs($this->dp)
            ->post(route('oevs.store'), $this->donneesOev(['handicap' => '1']))
            ->assertSessionHasErrors('types_handicap');

        $this->post(route('oevs.store'), $this->donneesOev(['handicap' => '1', 'types_handicap' => ['moteur', 'visuel'], 'nature_handicap' => 'Utilise un fauteuil']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['moteur', 'visuel'], Oev::firstOrFail()->types_handicap);
    }

    // ---------- Fiche d'identification (champs OEV) ----------

    public function test_le_statut_oev_est_calcule_a_partir_des_parents(): void
    {
        $this->actingAs($this->dp);
        $cas = [
            ['oui', 'non', 'orphelin_pere'],
            ['non', 'oui', 'orphelin_mere'],
            ['non', 'non', 'orphelin_double'],
            ['oui', 'oui', 'vulnerable'],
            ['ne_sait_pas', 'oui', 'vulnerable'],
        ];
        foreach ($cas as $i => [$mere, $pere, $attendu]) {
            // Un « statut » envoyé à la main est ignoré : seul le calcul fait foi
            $this->post(route('oevs.store'), $this->donneesOev(['nom' => "ENFANT{$i}", 'mere_vivante' => $mere, 'pere_vivant' => $pere, 'statut' => 'orphelin_double']))
                ->assertSessionHasNoErrors();
            $this->assertSame($attendu, Oev::where('nom', "ENFANT{$i}")->value('statut'), "mère={$mere}, père={$pere}");
        }
    }

    public function test_la_situation_des_parents_est_obligatoire(): void
    {
        $donnees = $this->donneesOev();
        unset($donnees['mere_vivante'], $donnees['pere_vivant']);

        $this->actingAs($this->dp)->post(route('oevs.store'), $donnees)
            ->assertSessionHasErrors([
                'mere_vivante' => 'Indiquez si la mère de l’enfant est vivante.',
                'pere_vivant' => 'Indiquez si le père de l’enfant est vivant.',
            ]);
    }

    public function test_un_enfant_non_scolarise_n_a_pas_besoin_d_etablissement(): void
    {
        $donnees = $this->donneesOev([
            'situation_scolaire' => 'non_scolarise',
            'raison_non_scolarisation' => 'financieres',
            'niveau_etude' => 'primaire',
        ]);
        unset($donnees['etablissement_actuel'], $donnees['type_etablissement'], $donnees['classe'], $donnees['frais_scolarite'], $donnees['systeme_educatif']);

        $this->actingAs($this->dp)->post(route('oevs.store'), $donnees)->assertSessionHasNoErrors();
        $oev = Oev::firstOrFail();
        $this->assertSame('non_scolarise', $oev->situation_scolaire);
        $this->assertSame('financieres', $oev->raison_non_scolarisation);
        $this->assertNull($oev->etablissement_actuel);
        $this->get(route('oevs.show', $oev))->assertOk()->assertSee('Contraintes financières')->assertSee('n’est pas scolarisé', false);
    }

    public function test_un_enfant_scolarise_doit_avoir_son_etablissement(): void
    {
        $this->actingAs($this->dp)
            ->post(route('oevs.store'), $this->donneesOev(['etablissement_actuel' => '', 'classe' => '']))
            ->assertSessionHasErrors(['etablissement_actuel', 'classe']);
    }

    public function test_les_champs_de_la_fiche_sont_enregistres_et_affiches(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev([
            'date_naissance_estimee' => '1',
            'lieu_naissance' => 'Kaya',
            'nationalite' => 'Burkinabè',
            'a_acte_naissance' => '1',
            'groupe_population' => 'pdi',
            'quartier' => 'Secteur 12',
            'lieu_provenance' => 'Djibo',
            'pere_nom' => 'OUEDRAOGO', 'pere_prenoms' => 'Salif', 'pere_date_deces' => '2023-04-10', 'pere_deces_confirme' => '1',
            'mere_nom' => 'SAWADOGO', 'mere_prenoms' => 'Mariam',
            'lieu_de_vie' => 'famille_elargie',
            'tuteur_sexe' => 'M', 'tuteur_lien' => 'oncle_tante', 'tuteur_cnib' => 'B1234567', 'tuteur_pret_continuer' => '1',
            'vulnerabilites' => ['orphelin', 'precarite'],
            'maladie_chronique' => '0',
            'source_revenu' => 'aide_famille', 'niveau_revenu' => 'faible', 'logement' => 'site_deplaces',
            'date_identification' => now()->subDays(3)->toDateString(), 'identifie_par' => 'communaute', 'niveau_priorite' => 'eleve',
            'formation_professionnelle' => '1', 'formation_etat' => 'en_cours', 'formation_filiere' => 'Couture', 'formation_type_centre' => 'public',
        ]))->assertSessionHasNoErrors();

        $oev = Oev::firstOrFail();
        $this->assertTrue($oev->date_naissance_estimee);
        $this->assertSame('pdi', $oev->groupe_population);
        $this->assertSame('2023-04-10', $oev->pere_date_deces->toDateString());
        $this->assertSame(['orphelin', 'precarite'], $oev->vulnerabilites);
        $this->assertTrue($oev->formation_professionnelle);

        $this->get(route('oevs.show', $oev))->assertOk()
            ->assertSee('Personne déplacée interne (PDI)')
            ->assertSee('Famille élargie ou étendue')
            ->assertSee('Précarité du ménage')
            ->assertSee('10/04/2023')
            ->assertSee('Oncle / tante')
            ->assertSee('Couture')
            ->assertSee('Secteur 12');
    }

    // ---------- Cohérence entre les réponses ----------

    public function test_sans_acte_de_naissance_la_piece_n_est_pas_demandee(): void
    {
        $pieces = $this->pieces();
        unset($pieces['acte_naissance']);

        // Enregistré et soumis sans acte : le dossier est complet quand même
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['a_acte_naissance' => '0', 'nom_structure_rib' => 'ONG Avenir'])
            + $pieces + ['soumettre' => '1'])->assertSessionHasNoErrors();

        $oev = Oev::firstOrFail();
        $this->assertArrayNotHasKey('acte_naissance', $oev->piecesRequises());
        $this->assertSame(Oev::ETAT_SOUMIS, $oev->statut_dossier);
        // « Absence d'acte de naissance » est cochée d'office
        $this->assertContains('sans_acte_naissance', $oev->vulnerabilites);
    }

    public function test_une_piece_non_demandee_envoyee_quand_meme_est_ignoree(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['a_acte_naissance' => '0', 'nom_structure_rib' => 'ONG'])
            + $this->pieces())->assertSessionHasNoErrors();

        $this->assertFalse(Oev::firstOrFail()->documents()->where('type', 'acte_naissance')->exists());
    }

    public function test_enfant_non_scolarise_et_tuteur_sans_cnib_moins_de_pieces(): void
    {
        $donnees = $this->donneesOev(['situation_scolaire' => 'non_scolarise', 'raison_non_scolarisation' => 'insecurite', 'tuteur_a_cnib' => '0', 'nom_structure_rib' => 'ONG']);
        unset($donnees['etablissement_actuel'], $donnees['classe'], $donnees['frais_scolarite'], $donnees['type_etablissement'], $donnees['systeme_educatif']);
        $pieces = array_intersect_key($this->pieces(), array_flip(['acte_naissance', 'photo', 'rib']));

        $this->actingAs($this->dp)->post(route('oevs.store'), $donnees + $pieces + ['soumettre' => '1'])->assertSessionHasNoErrors();

        $oev = Oev::firstOrFail();
        $this->assertSame(['acte_naissance', 'photo', 'rib'], array_keys($oev->piecesRequises()));
        $this->assertNull($oev->tuteur_cnib, 'Pas de numéro de CNIB si le tuteur n’en a pas');
        $this->assertSame(Oev::ETAT_SOUMIS, $oev->statut_dossier);
        $this->get(route('oevs.show', $oev))->assertSee('Non demandé pour cet enfant')->assertSee('le tuteur n’a pas de CNIB', false);
    }

    public function test_un_parent_decede_ne_peut_pas_etre_le_tuteur(): void
    {
        $this->actingAs($this->dp)
            ->post(route('oevs.store'), $this->donneesOev(['pere_vivant' => 'non', 'tuteur_lien' => 'pere']))
            ->assertSessionHasErrors(['tuteur_lien' => 'Le père est déclaré décédé : il ne peut pas être le tuteur de l’enfant.']);

        // La mère vivante peut l'être, et son sexe est déduit
        $this->post(route('oevs.store'), $this->donneesOev(['tuteur_lien' => 'mere', 'tuteur_sexe' => 'M']))->assertSessionHasNoErrors();
        $this->assertSame('F', Oev::firstOrFail()->tuteur_sexe);
    }

    public function test_les_dates_de_deces_sont_coherentes_avec_la_naissance(): void
    {
        $naissance = now()->subYears(5)->toDateString();
        $this->actingAs($this->dp);

        // Mère décédée avant la naissance : impossible
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => $naissance, 'mere_vivante' => 'non', 'mere_date_deces' => now()->subYears(6)->toDateString()]))
            ->assertSessionHasErrors('mere_date_deces');
        // Père décédé 2 ans avant la naissance : impossible ; 5 mois avant : possible
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => $naissance, 'pere_date_deces' => now()->subYears(7)->toDateString()]))
            ->assertSessionHasErrors('pere_date_deces');
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => $naissance, 'pere_date_deces' => now()->subYears(5)->subMonths(5)->toDateString()]))
            ->assertSessionHasNoErrors();
    }

    public function test_la_classe_doit_correspondre_au_niveau(): void
    {
        $this->actingAs($this->dp)
            ->post(route('oevs.store'), $this->donneesOev(['niveau_etude' => 'primaire', 'classe' => '6e']))
            ->assertSessionHasErrors(['classe' => 'La classe choisie ne correspond pas au niveau d’étude.']);

        $this->post(route('oevs.store'), $this->donneesOev(['niveau_etude' => 'primaire', 'classe' => 'CM2']))->assertSessionHasNoErrors();
    }

    public function test_les_vulnerabilites_automatiques_et_grossesse(): void
    {
        // Garçon : « grossesse » retirée ; orphelin + handicap ajoutés d'office même si non cochés
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev([
            'sexe' => 'M', 'handicap' => '1', 'types_handicap' => ['visuel'], 'vulnerabilites' => ['grossesse', 'travail'],
        ]))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['orphelin', 'handicap', 'travail'], Oev::firstOrFail()->vulnerabilites);

        // Parents vivants : « orphelin » ne peut pas être coché à la main
        $this->post(route('oevs.store'), $this->donneesOev(['nom' => 'KABORE', 'pere_vivant' => 'oui', 'vulnerabilites' => ['orphelin', 'negligence']]))->assertSessionHasNoErrors();
        $this->assertSame(['negligence'], Oev::where('nom', 'KABORE')->value('vulnerabilites'));
    }

    public function test_les_precisions_sont_exigees_quand_on_choisit_autre(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev([
            'lieu_de_vie' => 'autre', 'vulnerabilites' => ['autre'], 'tuteur_lien' => 'autre_parent', 'maladie_chronique' => '1', 'formation_professionnelle' => '1',
        ]))->assertSessionHasErrors(['lieu_de_vie_precision', 'vulnerabilite_precision', 'tuteur_lien_precision', 'maladie_details', 'formation_etat', 'formation_filiere', 'formation_type_centre']);
    }

    public function test_questions_de_base_obligatoires(): void
    {
        $donnees = $this->donneesOev(['situation_scolaire' => 'descolarise']);
        unset($donnees['a_acte_naissance'], $donnees['tuteur_a_cnib'], $donnees['tuteur_lien'], $donnees['niveau_etude']);

        $this->actingAs($this->dp)->post(route('oevs.store'), $donnees)
            ->assertSessionHasErrors(['a_acte_naissance', 'tuteur_a_cnib', 'tuteur_lien', 'niveau_etude', 'raison_non_scolarisation']);
    }

    public function test_les_informations_masquees_ne_sont_pas_conservees(): void
    {
        // Mère déclarée vivante : une date de décès envoyée par erreur n'est pas enregistrée
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev([
            'mere_vivante' => 'oui', 'mere_date_deces' => '2020-01-01',
            'lieu_de_vie' => 'famille_biologique', 'lieu_de_vie_precision' => 'ne doit pas rester',
            'formation_professionnelle' => '0', 'formation_filiere' => 'ne doit pas rester',
        ]))->assertSessionHasNoErrors();

        $oev = Oev::firstOrFail();
        $this->assertNull($oev->mere_date_deces);
        $this->assertNull($oev->lieu_de_vie_precision);
        $this->assertNull($oev->formation_filiere);
    }

    public function test_la_date_de_naissance_doit_etre_plausible(): void
    {
        $this->actingAs($this->dp);
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => now()->addDay()->toDateString()]))->assertSessionHasErrors('date_naissance');
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => '1980-05-12']))->assertSessionHasErrors('date_naissance');
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => 'pas une date']))->assertSessionHasErrors('date_naissance');
    }

    public function test_le_telephone_est_enregistre_avec_l_indicatif_du_burkina(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['contact_tuteur' => '70 12 34 56']))->assertSessionHasNoErrors();
        $this->post(route('oevs.store'), $this->donneesOev(['nom' => 'KABORE', 'contact_tuteur' => '+22676543210']))->assertSessionHasNoErrors();

        $this->assertSame('+226 70 12 34 56', Oev::where('nom', 'OUEDRAOGO')->value('contact_tuteur'));
        $this->assertSame('+226 76 54 32 10', Oev::where('nom', 'KABORE')->value('contact_tuteur'));
    }

    public function test_un_telephone_incomplet_est_refuse(): void
    {
        $this->actingAs($this->dp)
            ->post(route('oevs.store'), $this->donneesOev(['contact_tuteur' => '70 12 34']))
            ->assertSessionHasErrors('contact_tuteur');
    }

    public function test_les_messages_d_erreur_du_formulaire_sont_en_francais(): void
    {
        $this->actingAs($this->dp)
            ->post(route('oevs.store'), $this->donneesOev(['classe' => '', 'region' => '']))
            ->assertSessionHasErrors([
                'classe' => 'Le champ « classe » est obligatoire pour un enfant scolarisé.',
                'region' => 'Le champ « région » est obligatoire.',
            ]);
    }

    // ---------- Circuit DP → DR → central ----------

    public function test_circuit_complet_jusqu_a_l_integration_comme_oev(): void
    {
        $oev = $this->dossierComplet();

        // DP : soumission au DR
        $this->post(route('oevs.soumettre', $oev))->assertSessionHasNoErrors();
        $this->assertSame(Oev::ETAT_SOUMIS, $oev->refresh()->statut_dossier);
        $this->get(route('oevs.edit', $oev))->assertRedirect(route('oevs.show', $oev)); // plus modifiable

        // DR : le dossier apparaît à vérifier, il le déclare conforme
        $this->actingAs($this->dr)->get(route('oevs.validation'))->assertOk()->assertSee($oev->numero_dossier);
        $this->post(route('oevs.conforme', $oev))->assertRedirect(route('oevs.validation'));
        $this->assertSame(Oev::ETAT_VALIDE, $oev->refresh()->statut_dossier);
        $this->assertSame($this->dr->id, $oev->verifie_par);

        // Niveau central : intégration, l'enfant devient OEV
        $this->actingAs($this->central)->get(route('oevs.integration'))->assertOk()->assertSee($oev->numero_dossier);
        $this->post(route('oevs.integrer', $oev))->assertSessionHasNoErrors();

        $oev->refresh();
        $this->assertSame(Oev::ETAT_INTEGRE, $oev->statut_dossier);
        $this->assertSame('OEV-' . now()->year . '-0001', $oev->code);
        $this->get(route('oevs.integration', ['etat' => 'integre']))->assertSee($oev->code);
        $this->get(route('oevs.show', $oev))->assertSee($oev->code)->assertSee('Intégré');
    }

    public function test_enregistrer_et_soumettre_au_dr_en_une_fois(): void
    {
        $this->actingAs($this->dp)->get(route('oevs.create'))->assertSee('Enregistrer et soumettre au DR');

        // Dossier complet : enregistré puis directement soumis, visible du DR
        $this->post(route('oevs.store'), $this->donneesOev(['nom_structure_rib' => 'Association Espoir']) + $this->pieces() + ['soumettre' => '1'])
            ->assertSessionHasNoErrors();
        $complet = Oev::firstOrFail();
        $this->assertSame(Oev::ETAT_SOUMIS, $complet->statut_dossier);
        $this->actingAs($this->dr)->get(route('oevs.validation'))->assertSee($complet->numero_dossier);

        // Dossier incomplet : enregistré mais non soumis, avec un message clair
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['nom' => 'KABORE']) + ['soumettre' => '1'])
            ->assertSessionHasErrors('circuit');
        $this->assertSame(Oev::ETAT_BROUILLON, Oev::where('nom', 'KABORE')->value('statut_dossier'));
    }

    public function test_le_dr_est_informe_des_dossiers_encore_chez_le_dp(): void
    {
        $this->dossierComplet(); // complet mais non soumis

        $this->actingAs($this->dr)->get(route('oevs.validation'))
            ->assertSee('Aucun dossier n’est en attente de vérification', false)
            ->assertSee('Soumettre au DR');
        $this->actingAs($this->dp)->get(route('oevs.index'))->assertSee('Prêt à soumettre au DR');
    }

    public function test_un_dossier_incomplet_ne_peut_pas_etre_soumis(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev());
        $oev = Oev::firstOrFail();

        $this->post(route('oevs.soumettre', $oev))->assertSessionHasErrors('circuit');
        $this->assertSame(Oev::ETAT_BROUILLON, $oev->refresh()->statut_dossier);
    }

    public function test_un_dossier_non_conforme_revient_au_dp_avec_le_motif(): void
    {
        $oev = $this->dossierComplet();
        $this->post(route('oevs.soumettre', $oev));

        // Le motif est obligatoire
        $this->actingAs($this->dr)->post(route('oevs.non-conforme', $oev), ['motif_non_conformite' => ''])->assertSessionHasErrors('motif_non_conformite');

        $this->post(route('oevs.non-conforme', $oev), ['motif_non_conformite' => 'Acte de naissance illisible'])->assertRedirect(route('oevs.validation'));
        $this->assertSame(Oev::ETAT_NON_CONFORME, $oev->refresh()->statut_dossier);

        // Le DP voit le motif, corrige et soumet à nouveau
        $this->actingAs($this->dp)->get(route('oevs.show', $oev))->assertSee('Acte de naissance illisible')->assertSee('Soumettre au DR');
        $this->put(route('oevs.update', $oev), $this->donneesOev(['nom_structure_rib' => 'Association Espoir'])
            + ['acte_naissance' => UploadedFile::fake()->create('acte-lisible.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $this->post(route('oevs.soumettre', $oev))->assertSessionHasNoErrors();
        $this->assertSame(Oev::ETAT_SOUMIS, $oev->refresh()->statut_dossier);
    }

    /** Dossier validé par le DR, en attente d'intégration au central. */
    private function dossierValide(): Oev
    {
        $oev = $this->dossierComplet();
        $this->post(route('oevs.soumettre', $oev));
        $this->actingAs($this->dr)->post(route('oevs.conforme', $oev));

        return $oev->refresh();
    }

    public function test_le_central_demande_un_complement_que_le_dr_apporte_lui_meme(): void
    {
        $oev = $this->dossierValide();

        // Central : la demande exige un motif, puis renvoie le dossier au DR
        $this->actingAs($this->central)->get(route('oevs.show', $oev))->assertSee('Demander un complément');
        $this->post(route('oevs.complement', $oev), ['motif_complement' => ''])->assertSessionHasErrors('motif_complement');
        $this->post(route('oevs.complement', $oev), ['motif_complement' => 'Préciser la classe de l’année en cours'])
            ->assertRedirect(route('oevs.integration'));
        $oev->refresh();
        $this->assertSame(Oev::ETAT_COMPLEMENT, $oev->statut_dossier);
        $this->assertSame($this->central->id, $oev->complement_par);
        $this->post(route('oevs.integrer', $oev))->assertSessionHasErrors('circuit'); // plus intégrable tant que non complété

        // DR : voit la demande, complète lui-même le dossier puis le revalide
        $this->actingAs($this->dr)->get(route('oevs.validation'))->assertSee('Voir les compléments demandés');
        $this->get(route('oevs.validation', ['etat' => 'complement']))->assertSee($oev->numero_dossier);
        $this->get(route('oevs.show', $oev))->assertSee('Préciser la classe de l’année en cours')->assertSee('Valider et renvoyer au central');
        $this->get(route('oevs.edit', $oev))->assertOk()->assertSee('Complément demandé par le niveau central');
        $this->put(route('oevs.update', $oev), $this->donneesOev(['niveau_etude' => 'primaire', 'classe' => 'CM2', 'nom_structure_rib' => 'Association Espoir']))
            ->assertSessionHasNoErrors();
        $this->assertSame('CM2', $oev->refresh()->classe);
        $this->assertSame(Oev::ETAT_COMPLEMENT, $oev->statut_dossier);

        $this->post(route('oevs.conforme', $oev))->assertRedirect(route('oevs.validation'));
        $this->assertSame(Oev::ETAT_VALIDE, $oev->refresh()->statut_dossier);

        // Central : intégration possible
        $this->actingAs($this->central)->post(route('oevs.integrer', $oev))->assertSessionHasNoErrors();
        $this->assertSame(Oev::ETAT_INTEGRE, $oev->refresh()->statut_dossier);
        $this->assertNotNull($oev->code);
    }

    public function test_le_dr_renvoie_au_dp_un_complement_qu_il_ne_peut_pas_apporter(): void
    {
        $oev = $this->dossierValide();
        $this->actingAs($this->central)->post(route('oevs.complement', $oev), ['motif_complement' => 'RIB de la structure expiré']);

        $this->actingAs($this->dr)->post(route('oevs.non-conforme', $oev), ['motif_non_conformite' => 'Le DP doit fournir un RIB à jour'])
            ->assertRedirect(route('oevs.validation'));
        $this->assertSame(Oev::ETAT_NON_CONFORME, $oev->refresh()->statut_dossier);

        // Le DP corrige et resoumet : le circuit normal reprend (DR puis central)
        $this->actingAs($this->dp)->get(route('oevs.show', $oev))->assertSee('Le DP doit fournir un RIB à jour');
        $this->put(route('oevs.update', $oev), $this->donneesOev(['nom_structure_rib' => 'Association Espoir'])
            + ['rib' => UploadedFile::fake()->create('rib-2026.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $this->post(route('oevs.soumettre', $oev))->assertSessionHasNoErrors();
        $this->assertSame(Oev::ETAT_SOUMIS, $oev->refresh()->statut_dossier);
    }

    public function test_seul_le_dr_modifie_un_dossier_en_complement(): void
    {
        $oev = $this->dossierValide();
        $this->actingAs($this->central)->post(route('oevs.complement', $oev), ['motif_complement' => 'Photo floue']);

        // Ni le DP ni le central ne peuvent modifier le dossier à cette étape
        $this->actingAs($this->dp)->get(route('oevs.edit', $oev))->assertRedirect(route('oevs.show', $oev));
        $this->put(route('oevs.update', $oev), $this->donneesOev(['classe' => 'X']))->assertRedirect(route('oevs.show', $oev));
        $this->actingAs($this->central)->get(route('oevs.edit', $oev))->assertForbidden();
        $this->assertSame('6e', $oev->refresh()->classe);

        // Et le DR ne peut plus modifier un dossier une fois validé
        $this->actingAs($this->dr)->post(route('oevs.conforme', $oev));
        $this->get(route('oevs.edit', $oev))->assertRedirect(route('oevs.show', $oev));
    }

    // ---------- Rejet définitif ----------

    public function test_le_dr_peut_rejeter_un_dossier_soumis(): void
    {
        $oev = $this->dossierComplet();
        $this->post(route('oevs.soumettre', $oev));

        $this->actingAs($this->dr)->get(route('oevs.show', $oev))->assertSee('Rejeter');
        $this->post(route('oevs.rejeter', $oev), ['motif_rejet' => ''])->assertSessionHasErrors('motif_rejet');
        $this->post(route('oevs.rejeter', $oev), ['motif_rejet' => 'Enfant ne remplissant pas les critères'])
            ->assertRedirect(route('oevs.validation'));

        $oev->refresh();
        $this->assertSame(Oev::ETAT_REJETE, $oev->statut_dossier);
        $this->assertSame('DR', $oev->rejete_niveau);
        $this->assertSame($this->dr->id, $oev->rejete_par);
        $this->get(route('oevs.validation', ['etat' => 'rejete']))->assertSee($oev->numero_dossier);

        // Dossier clos : le DP voit le motif mais ne peut plus rien modifier ni soumettre
        $this->actingAs($this->dp)->get(route('oevs.show', $oev))
            ->assertSee('Enfant ne remplissant pas les critères')->assertSee('Dossier rejeté par le DR')
            ->assertDontSee('Soumettre au DR')->assertDontSee('Modifier / compléter');
        $this->get(route('oevs.edit', $oev))->assertRedirect(route('oevs.show', $oev));
        $this->post(route('oevs.soumettre', $oev))->assertSessionHasErrors('circuit');
    }

    public function test_le_dr_peut_rejeter_un_dossier_revenu_du_central(): void
    {
        $oev = $this->dossierValide();
        $this->actingAs($this->central)->post(route('oevs.complement', $oev), ['motif_complement' => 'Pièces douteuses']);

        $this->actingAs($this->dr)->post(route('oevs.rejeter', $oev), ['motif_rejet' => 'Documents falsifiés'])->assertSessionHasNoErrors();
        $this->assertSame(Oev::ETAT_REJETE, $oev->refresh()->statut_dossier);
        $this->assertSame('DR', $oev->rejete_niveau);
    }

    public function test_le_central_peut_rejeter_un_dossier_valide(): void
    {
        $oev = $this->dossierValide();

        $this->actingAs($this->central)->get(route('oevs.show', $oev))->assertSee('Rejeter');
        $this->post(route('oevs.rejeter', $oev), ['motif_rejet' => 'Doublon avec un OEV existant'])
            ->assertRedirect(route('oevs.integration'));

        $oev->refresh();
        $this->assertSame(Oev::ETAT_REJETE, $oev->statut_dossier);
        $this->assertSame('Central', $oev->rejete_niveau);
        $this->assertNull($oev->code, 'Un dossier rejeté ne reçoit pas de code OEV');
        $this->post(route('oevs.integrer', $oev))->assertSessionHasErrors('circuit');
        $this->get(route('oevs.integration', ['etat' => 'rejete']))->assertSee($oev->numero_dossier);
        $this->get(route('oevs.liste'))->assertDontSee($oev->numero_dossier)->assertDontSee('OUEDRAOGO');
        $this->get(route('oevs.show', $oev))->assertSee('Rejeté par le niveau central');
    }

    public function test_chacun_ne_rejette_qu_a_son_etape(): void
    {
        $oev = $this->dossierComplet();

        // Le DP ne rejette jamais
        $this->post(route('oevs.rejeter', $oev), ['motif_rejet' => 'x'])->assertForbidden();
        // Dossier non soumis : ni le DR ni le central ne peuvent le rejeter
        $this->actingAs($this->dr)->post(route('oevs.rejeter', $oev), ['motif_rejet' => 'x'])->assertSessionHasErrors('circuit');

        // Dossier soumis : le central ne peut pas encore le rejeter (c'est au DR)
        $this->actingAs($this->dp)->post(route('oevs.soumettre', $oev));
        $this->actingAs($this->central)->post(route('oevs.rejeter', $oev), ['motif_rejet' => 'x'])->assertSessionHasErrors('circuit');

        // Dossier validé : c'est au central, plus au DR
        $this->actingAs($this->dr)->post(route('oevs.conforme', $oev));
        $this->post(route('oevs.rejeter', $oev), ['motif_rejet' => 'x'])->assertSessionHasErrors('circuit');
        $this->assertSame(Oev::ETAT_VALIDE, $oev->refresh()->statut_dossier);
    }

    public function test_les_etapes_ne_peuvent_pas_etre_sautees(): void
    {
        $oev = $this->dossierComplet();

        // Pas de validation DR ni d'intégration sur un dossier non soumis
        $this->actingAs($this->dr)->post(route('oevs.conforme', $oev))->assertSessionHasErrors('circuit');
        $this->actingAs($this->central)->post(route('oevs.integrer', $oev))->assertSessionHasErrors('circuit');
        $this->assertSame(Oev::ETAT_BROUILLON, $oev->refresh()->statut_dossier);
        $this->assertNull($oev->code);
    }

    public function test_chaque_niveau_n_a_acces_qu_a_son_etape(): void
    {
        $oev = $this->dossierComplet();
        $this->post(route('oevs.soumettre', $oev));

        // Le DP ne valide pas et n'intègre pas
        $this->actingAs($this->dp);
        $this->get(route('oevs.validation'))->assertForbidden();
        $this->post(route('oevs.conforme', $oev))->assertForbidden();
        $this->get(route('oevs.integration'))->assertForbidden();

        // Le DR ne constitue pas et n'intègre pas
        $this->actingAs($this->dr);
        $this->get(route('oevs.create'))->assertForbidden();
        $this->post(route('oevs.integrer', $oev))->assertForbidden();

        // Le niveau central ne constitue pas et ne valide pas
        $this->actingAs($this->central);
        $this->post(route('oevs.store'), $this->donneesOev())->assertForbidden();
        $this->post(route('oevs.conforme', $oev))->assertForbidden();
    }

    public function test_le_dr_ne_constitue_pas_et_ne_voit_que_les_dossiers_soumis(): void
    {
        $oev = $this->dossierComplet();

        $this->actingAs($this->dr);
        // Pas d'accès à « Constituer dossier enfant » (ni menu, ni page)
        $this->get(route('oevs.validation'))->assertOk()
            ->assertDontSee('Constituer dossier enfant')
            ->assertSee('Validation des dossiers');
        $this->get(route('oevs.index'))->assertForbidden();
        $this->get(route('oevs.create'))->assertForbidden();
        $this->post(route('oevs.store'), $this->donneesOev())->assertForbidden();
        $this->put(route('oevs.update', $oev), $this->donneesOev())->assertForbidden();

        // Un dossier non soumis ne lui est pas visible…
        $this->get(route('oevs.show', $oev))->assertForbidden();

        // …il le voit une fois soumis, avec ses boutons de décision
        $this->actingAs($this->dp)->post(route('oevs.soumettre', $oev));
        $this->actingAs($this->dr)->get(route('oevs.show', $oev))->assertOk()
            ->assertSee('Conforme — valider')->assertSee('Non conforme')
            ->assertDontSee('Modifier / compléter')
            ->assertSee(route('oevs.validation'));
    }

    public function test_le_central_integre_sans_pouvoir_constituer_ni_valider(): void
    {
        $oev = $this->dossierComplet();
        $this->post(route('oevs.soumettre', $oev));
        $this->actingAs($this->dr)->post(route('oevs.conforme', $oev));

        $this->actingAs($this->central);
        $this->get(route('oevs.integration'))->assertOk()
            ->assertDontSee('Constituer dossier enfant')
            ->assertDontSee('Validation des dossiers')
            ->assertSee('Intégration des OEV');
        $this->get(route('oevs.index'))->assertForbidden();
        $this->get(route('oevs.show', $oev))->assertOk()
            ->assertSee('Intégrer comme OEV')
            ->assertDontSee('Conforme — valider');
    }

    public function test_la_liste_des_oev_ne_contient_que_les_enfants_integres(): void
    {
        $integre = $this->dossierComplet();
        $this->post(route('oevs.soumettre', $integre));
        $this->actingAs($this->dr)->post(route('oevs.conforme', $integre));
        $this->actingAs($this->central)->post(route('oevs.integrer', $integre));
        $integre->refresh();

        $enCours = $this->dossierComplet(['nom' => 'KABORE', 'prenom' => 'Paul', 'sexe' => 'M', 'pere_vivant' => 'oui']);
        $this->post(route('oevs.soumettre', $enCours));

        // Visible de tous les niveaux (DP, DR, central)
        foreach ([$this->dp, $this->dr, $this->central] as $utilisateur) {
            $this->actingAs($utilisateur)->get(route('oevs.liste'))->assertOk()
                ->assertSee('Liste des OEV')
                ->assertSeeInOrder([$integre->code, 'OUEDRAOGO', 'Awa', 'Orphelin de père'])
                ->assertDontSee('KABORE')
                ->assertDontSee($enCours->numero_dossier);
        }

        // Filtres par statut et recherche
        $this->get(route('oevs.liste', ['statut' => 'vulnerable']))->assertDontSee('OUEDRAOGO');
        $this->get(route('oevs.liste', ['q' => $integre->code]))->assertSee('OUEDRAOGO');

        // Depuis la fiche d'un OEV, le retour mène à la liste des OEV
        $this->get(route('oevs.show', $integre))->assertSee(route('oevs.liste'));
    }

    // ---------- Listes ----------

    public function test_constituer_dossier_enfant_regroupe_dossiers_et_nouveau_dossier(): void
    {
        $this->actingAs($this->dp);

        $this->get(route('oevs.index'))->assertOk()
            ->assertSee('Constituer dossier enfant')
            ->assertSee(route('oevs.create'))
            ->assertSeeInOrder(['Dossiers enfants', 'Nouveau dossier']);

        $this->get(route('oevs.create'))->assertOk()->assertSee(route('oevs.index'));
    }

    public function test_la_liste_filtre_par_etat_du_dossier(): void
    {
        $soumis = $this->dossierComplet();
        $this->post(route('oevs.soumettre', $soumis));
        $this->post(route('oevs.store'), $this->donneesOev(['nom' => 'KABORE', 'prenom' => 'Paul', 'sexe' => 'M']));

        $this->get(route('oevs.index'))->assertOk()->assertSee('OUEDRAOGO')->assertSee('KABORE');
        $this->get(route('oevs.index', ['etat' => 'soumis']))->assertSee('OUEDRAOGO')->assertDontSee('KABORE');
        $this->get(route('oevs.index', ['etat' => 'brouillon']))->assertSee('KABORE')->assertDontSee('OUEDRAOGO');
    }

    public function test_la_liste_affiche_l_essentiel_et_la_fiche_le_detail(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev());
        $oev = Oev::firstOrFail();

        $this->get(route('oevs.index'))
            ->assertSeeInOrder([$oev->numero_dossier, 'OUEDRAOGO', 'Awa', 'Orphelin de père', 'En constitution'])
            ->assertSee(route('oevs.show', $oev))
            ->assertDontSee('SAWADOGO')
            ->assertDontSee('Lycée Philippe');

        $this->get(route('oevs.show', $oev))
            ->assertSee('SAWADOGO')->assertSee('+226 70 00 00 00')->assertSee('Lycée Philippe Zinda Kaboré')->assertSee('Kadiogo')
            ->assertSee('Parcours du dossier');
    }
}
