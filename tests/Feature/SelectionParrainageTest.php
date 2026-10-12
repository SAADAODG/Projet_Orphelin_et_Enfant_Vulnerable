<?php

namespace Tests\Feature;

use App\Models\AppuiPartenaire;
use App\Models\BeneficiaireSession;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Services\Parrainage\ScoreBesoin;
use App\Services\Parrainage\SelectionParrainage;
use App\Services\Parrainage\SourceSelection;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelectionParrainageTest extends TestCase
{
    use RefreshDatabase;

    private User $central;
    private User $dp;
    private Region $region;
    private Region $autreRegion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class]);

        [$this->region, $this->autreRegion] = Region::has('provinces')->orderBy('id')->take(2)->get()->all();
        $this->central = User::factory()->create()->assignRole('agent DGFE');
        $this->dp = User::factory()->create(['region_id' => $this->region->id, 'province_id' => $this->region->provinces()->value('id')])->assignRole('DP');
    }

    private function nouvelleSession(array $surcharge = []): SessionParrainage
    {
        return SessionParrainage::create($surcharge + [
            'description' => 'Session principale', 'annee' => '2026-2027', 'numero' => 1,
            'type_appui' => SessionParrainage::TYPE_SCOLAIRE, 'enveloppe' => 1_000_000, 'plafond_beneficiaire' => 100_000,
            'source_financement' => SessionParrainage::SOURCE_ETAT, 'date_ouverture' => '2026-10-01',
            'etat' => SessionParrainage::ETAT_EN_COURS,
        ]);
    }

    private function oev(string $nom, array $champs = [], ?Region $region = null): Oev
    {
        $region ??= $this->region;

        return Oev::create($champs + [
            'nom' => $nom, 'prenom' => 'Test', 'sexe' => 'F', 'date_naissance' => now()->subYears(10)->toDateString(),
            'statut' => 'vulnerable', 'handicap' => false, 'situation_scolaire' => 'scolarise', 'classe' => 'CM1',
            'frais_scolarite' => 40_000, 'niveau_priorite' => 'moyen',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'region_id' => $region->id, 'province_id' => $region->provinces()->value('id'),
            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
            'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
        ]);
    }

    private function eligibles(SessionParrainage $session, array $filtres = []): array
    {
        return app(SelectionParrainage::class)->eligibles($session, $filtres)->pluck('nom')->all();
    }

    public function test_le_score_d_urgence_est_le_meme_en_base_et_dans_le_detail(): void
    {
        $oev = $this->oev('URGENT', [
            'niveau_priorite' => 'eleve', 'statut' => 'orphelin_double', 'handicap' => true, 'maladie_chronique' => true,
            'source_revenu' => 'mendicite', 'niveau_revenu' => 'faible', 'logement' => 'sans_abri',
            'vulnerabilites' => ['orphelin', 'abandon', 'travail'], 'tuteur_pret_continuer' => false,
        ]);

        $enBase = (int) app(SelectionParrainage::class)->eligibles($this->nouvelleSession())->first()->score;

        $this->assertSame(20 + 20 + 10 + 5 + 10 + 10 + 10 + 4 + 5, ScoreBesoin::calculer($oev));
        $this->assertSame(ScoreBesoin::calculer($oev), $enBase);
        $this->assertSame(['Critique', 'danger'], ScoreBesoin::niveau($enBase));
    }

    public function test_les_eligibles_dependent_du_type_d_appui_et_sont_classes_par_urgence(): void
    {
        $this->oev('PEU_URGENT', ['niveau_priorite' => 'faible']);
        $this->oev('TRES_URGENT', ['niveau_priorite' => 'eleve', 'statut' => 'orphelin_double']);
        $this->oev('EN_FORMATION', ['situation_scolaire' => 'non_scolarise', 'formation_professionnelle' => true, 'formation_etat' => 'en_cours']);
        $this->oev('DESACTIVE', ['desactivation_etat' => Oev::DESACTIVE]);
        $this->oev('NON_INTEGRE', ['statut_dossier' => Oev::ETAT_VALIDE, 'code' => null]);

        $this->assertSame(['TRES_URGENT', 'PEU_URGENT'], $this->eligibles($this->nouvelleSession()));
        $this->assertSame(['EN_FORMATION'], $this->eligibles($this->nouvelleSession(['numero' => 2, 'type_appui' => SessionParrainage::TYPE_FORMATION])));
        $this->assertCount(3, $this->eligibles($this->nouvelleSession(['numero' => 3, 'type_appui' => SessionParrainage::TYPE_LES_DEUX])));
    }

    public function test_les_filtres_acceptent_plusieurs_regions_provinces_et_valeurs(): void
    {
        $session = $this->nouvelleSession();
        $partielle = Region::has('provinces', '>=', 2)->whereKeyNot($this->region->id)->firstOrFail();
        $troisieme = Region::has('provinces')->whereNotIn('id', [$this->region->id, $partielle->id])->firstOrFail();
        [$p1, $p2] = $partielle->provinces()->orderBy('id')->take(2)->get()->all();

        $this->oev('ICI', ['vulnerabilites' => ['orphelin']]);
        $this->oev('PARTIELLE_P1', ['province_id' => $p1->id, 'vulnerabilites' => ['abandon'], 'sexe' => 'M'], $partielle);
        $this->oev('PARTIELLE_P2', ['province_id' => $p2->id, 'vulnerabilites' => ['travail']], $partielle);
        $this->oev('AILLEURS', ['niveau_priorite' => 'eleve'], $troisieme);

        // Une région entière + une seule province d'une autre région
        $this->assertEqualsCanonicalizing(['ICI', 'PARTIELLE_P1'],
            $this->eligibles($session, ['regions' => [$this->region->id, $partielle->id], 'provinces' => [$p1->id]]));
        // Deux régions entières
        $this->assertEqualsCanonicalizing(['ICI', 'PARTIELLE_P1', 'PARTIELLE_P2'],
            $this->eligibles($session, ['regions' => [$this->region->id, $partielle->id]]));
        // Plusieurs valeurs d'un même filtre : l'une ou l'autre
        $this->assertEqualsCanonicalizing(['ICI', 'PARTIELLE_P1'], $this->eligibles($session, ['vulnerabilite' => ['orphelin', 'abandon']]));
        $this->assertEqualsCanonicalizing(['ICI', 'PARTIELLE_P2', 'AILLEURS'], $this->eligibles($session, ['sexe' => ['F']]));
        $this->assertEqualsCanonicalizing(['ICI', 'PARTIELLE_P1', 'PARTIELLE_P2', 'AILLEURS'], $this->eligibles($session, ['niveau_priorite' => ['moyen', 'eleve']]));

        // Par l'écran : filtres multiples affichés, puis « tout ajouter » avec les mêmes filtres
        $filtres = ['onglet' => 'eligibles', 'regions' => [$this->region->id, $partielle->id], 'provinces' => [$p1->id]];
        $this->actingAs($this->central)->get(route('parrainage.sessions.selection', ['session' => $session] + $filtres))
            ->assertOk()->assertSee('PARTIELLE_P1')->assertDontSee('PARTIELLE_P2')->assertDontSee('AILLEURS')->assertSee('Tout ajouter (2)');
        $this->post(route('parrainage.sessions.selection.ajouter', $session), ['tous' => 1] + $filtres)->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['ICI', 'PARTIELLE_P1'], $session->beneficiaires()->with('oev')->get()->pluck('oev.nom')->all());
    }

    public function test_tous_les_oev_integres_sont_listes_et_les_non_eligibles_portent_leur_motif(): void
    {
        $premiere = $this->nouvelleSession();
        $deja = $this->oev('DEJA_RETENU');
        // Non scolarisé et au-delà de l'âge scolaire (6 à 16 ans) : pas concerné par l'appui scolaire
        $this->oev('NON_SCOLARISE', ['situation_scolaire' => 'non_scolarise', 'date_naissance' => now()->subYears(18)->toDateString()]);
        $this->oev('ELIGIBLE');
        $this->oev('NON_INTEGRE', ['statut_dossier' => Oev::ETAT_VALIDE, 'code' => null]);
        $selection = app(SelectionParrainage::class);
        $selection->ajouter($premiere, [$deja->id], $this->central);
        $session = $this->nouvelleSession(['numero' => 2]);

        $candidats = $selection->candidats($session)->get();
        $this->assertEqualsCanonicalizing(['DEJA_RETENU', 'NON_SCOLARISE', 'ELIGIBLE'], $candidats->pluck('nom')->all());
        $this->assertSame(['ELIGIBLE'], $selection->candidats($session, ['eligibilite' => 'oui'])->pluck('nom')->all());
        $this->assertEqualsCanonicalizing(['DEJA_RETENU', 'NON_SCOLARISE'], $selection->candidats($session, ['eligibilite' => 'non'])->pluck('nom')->all());

        $motifs = $selection->motifsInegibilite($session, $candidats);
        $this->assertSame('Déjà retenu par la session ' . $premiere->reference(), $motifs[$deja->id]);
        $this->assertSame('Non scolarisé et hors de l’âge scolaire (6 à 16 ans)', $motifs[$candidats->firstWhere('nom', 'NON_SCOLARISE')->id]);
        $this->assertCount(2, $motifs);

        $this->actingAs($this->central)->get(route('parrainage.sessions.selection', $session))
            ->assertOk()->assertSee('DEJA_RETENU')->assertSee('Non éligible : Non scolarisé et hors de l’âge scolaire')->assertSee('Tout ajouter (1)');

        // Un non éligible coché quand même n'est pas ajouté
        $this->post(route('parrainage.sessions.selection.ajouter', $session), ['oev_ids' => [$deja->id]]);
        $this->assertSame(0, $session->beneficiaires()->count());
    }

    public function test_les_filtres_vulnerabilite_age_priorite_et_scolarite(): void
    {
        $session = $this->nouvelleSession();
        $this->oev('PETIT_ORPHELIN', ['date_naissance' => now()->subYears(6)->toDateString(), 'statut' => 'orphelin_mere', 'vulnerabilites' => ['orphelin', 'abandon']]);
        $this->oev('GRAND_HANDICAP', ['date_naissance' => now()->subYears(15)->toDateString(), 'handicap' => true, 'niveau_priorite' => 'eleve']);
        $this->oev('ORDINAIRE', ['niveau_priorite' => 'faible']);
        $this->oev('A_INSCRIRE', ['situation_scolaire' => 'non_scolarise', 'classe' => null, 'frais_scolarite' => null]);

        $this->assertSame(['PETIT_ORPHELIN'], $this->eligibles($session, ['vulnerabilite' => 'abandon']));
        $this->assertSame(['PETIT_ORPHELIN'], $this->eligibles($session, ['age_max' => 8]));
        $this->assertSame(['GRAND_HANDICAP'], $this->eligibles($session, ['age_min' => 12, 'handicap' => '1']));
        $this->assertSame(['GRAND_HANDICAP'], $this->eligibles($session, ['niveau_priorite' => 'eleve']));
        $this->assertSame(['A_INSCRIRE'], $this->eligibles($session, ['scolarite' => SelectionParrainage::A_SCOLARISER]));
        $this->assertNotContains('A_INSCRIRE', $this->eligibles($session, ['scolarite' => SelectionParrainage::SCOLARISE]));
    }

    public function test_les_enfants_d_age_scolaire_non_scolarises_sont_eligibles_avec_un_montant_estimatif(): void
    {
        $session = $this->nouvelleSession();
        $inscrire = $this->oev('A_INSCRIRE', ['situation_scolaire' => 'descolarise', 'classe' => null, 'frais_scolarite' => null]);
        $this->oev('TROP_JEUNE', ['situation_scolaire' => 'non_scolarise', 'classe' => null, 'date_naissance' => now()->subYears(4)->toDateString()]);
        $this->oev('EN_FORMATION', ['situation_scolaire' => 'descolarise', 'formation_professionnelle' => true, 'formation_etat' => 'en_cours', 'date_naissance' => now()->subYears(15)->toDateString()]);

        $this->assertNotContains('TROP_JEUNE', $this->eligibles($session));
        $this->assertNotContains('EN_FORMATION', $this->eligibles($session), 'Une session scolaire ne finance pas la formation');
        $this->assertContains('A_INSCRIRE', $this->eligibles($session));

        // Montant estimatif saisi pour la session, repris à l'ajout
        $this->actingAs($this->central)->put(route('parrainage.sessions.selection.estimations', $session), ['estimation_scolarisation' => '25 000'])
            ->assertSessionHas('success');
        $this->assertSame(25_000, $session->refresh()->estimation_scolarisation);
        $this->post(route('parrainage.sessions.selection.ajouter', $session), ['oev_ids' => [$inscrire->id]]);
        $ligne = $session->beneficiaires()->where('oev_id', $inscrire->id)->first();
        $this->assertSame([25_000, true, 25_000, 'scolaire'], [$ligne->frais_reels, $ligne->frais_estimes, $ligne->montant_retenu, $ligne->type_appui]);

        // Estimation modifiée : la ligne suit ; une fois les frais saisis pour l'OEV, elle ne suit plus
        $this->put(route('parrainage.sessions.selection.estimations', $session), ['estimation_scolarisation' => '30000']);
        $this->assertSame(30_000, $ligne->refresh()->montant_retenu);
        $this->patch(route('parrainage.sessions.selection.modifier', [$session, $ligne]), ['frais_reels' => '18 000']);
        $this->put(route('parrainage.sessions.selection.estimations', $session), ['estimation_scolarisation' => '50000']);
        $this->assertSame([18_000, false], [$ligne->refresh()->montant_retenu, $ligne->frais_estimes]);
    }

    public function test_l_estimation_du_cout_cumule_scolarite_et_montants_estimatifs(): void
    {
        $session = $this->nouvelleSession(['type_appui' => SessionParrainage::TYPE_LES_DEUX, 'estimation_scolarisation' => 25_000, 'estimation_formation' => 150_000]);
        $this->oev('SCOLARISE', ['frais_scolarite' => 40_000]);
        $this->oev('SCOLARISE_CHER', ['frais_scolarite' => 120_000]);
        $this->oev('SANS_FRAIS', ['frais_scolarite' => null]);
        $this->oev('A_INSCRIRE', ['situation_scolaire' => 'non_scolarise', 'classe' => null, 'frais_scolarite' => null]);
        $this->oev('EN_FORMATION', ['situation_scolaire' => 'descolarise', 'formation_professionnelle' => true, 'formation_etat' => 'en_cours', 'date_naissance' => now()->subYears(17)->toDateString()]);
        $selection = app(SelectionParrainage::class);

        $estimation = $selection->estimationCout($session, $selection->eligibles($session));

        $this->assertSame(5, $estimation['nombre']);
        $this->assertSame(['nombre' => 3, 'frais' => 160_000, 'sans_frais' => 1, 'pris_en_charge' => 40_000 + 100_000 + 100_000], $estimation['groupes'][SelectionParrainage::SCOLARISE]);
        $this->assertSame(['nombre' => 1, 'frais' => 25_000, 'sans_frais' => 0, 'pris_en_charge' => 25_000], $estimation['groupes'][SelectionParrainage::A_SCOLARISER]);
        // Formation : estimation (150 000) plafonnée à 100 000
        $this->assertSame(['nombre' => 1, 'frais' => 150_000, 'sans_frais' => 0, 'pris_en_charge' => 100_000], $estimation['groupes'][SelectionParrainage::EN_FORMATION]);
        $this->assertSame(365_000, $estimation['pris_en_charge']);

        $this->actingAs($this->central)->get(route('parrainage.sessions.selection', $session))
            ->assertOk()->assertSee('Estimation du coût')->assertSee("365\u{00A0}000\u{00A0}FCFA", false);
    }

    public function test_sont_exclus_les_oev_deja_retenus_ailleurs_ou_appuyes_par_un_partenaire(): void
    {
        $retenuAilleurs = $this->oev('RETENU_AILLEURS');
        $appuye = $this->oev('APPUYE_PARTENAIRE');
        $this->oev('LIBRE');

        $autre = $this->nouvelleSession(['numero' => 9]);
        $autre->beneficiaires()->create(['oev_id' => $retenuAilleurs->id, 'type_appui' => 'scolaire', 'statut' => BeneficiaireSession::RETENU, 'montant_retenu' => 10]);
        AppuiPartenaire::create(['oev_id' => $appuye->id, 'parrain_id' => Parrain::create(['type' => 'ong', 'nom' => 'ONG'])->id,
            'annee' => '2026-2027', 'nature_appui_id' => NatureAppui::where('code', 'scolaire')->value('id')]);

        $this->assertSame(['APPUYE_PARTENAIRE', 'LIBRE'], collect($this->eligibles($this->nouvelleSession()))->sort()->values()->all());
        // RG-07 : la session exclut les OEV déjà appuyés par un partenaire pour la même nature
        $this->assertSame(['LIBRE'], $this->eligibles($this->nouvelleSession(['numero' => 2, 'exclure_deja_appuyes' => true])));
    }

    public function test_l_ajout_respecte_l_enveloppe_et_place_le_reste_en_liste_d_attente(): void
    {
        // Enveloppe de 150 000, plafond 100 000 : frais 40 000 → 40 000 ; frais inconnus → plafond (100 000)
        $session = $this->nouvelleSession(['enveloppe' => 150_000, 'bloquer_depassement_enveloppe' => true]);
        $a = $this->oev('A', ['niveau_priorite' => 'eleve', 'frais_scolarite' => 40_000]);
        $b = $this->oev('B', ['niveau_priorite' => 'moyen', 'frais_scolarite' => null]);
        $c = $this->oev('C', ['niveau_priorite' => 'faible', 'frais_scolarite' => 30_000]);

        $this->actingAs($this->central)->post(route('parrainage.sessions.selection.ajouter', $session), ['oev_ids' => [$a->id, $b->id, $c->id]])
            ->assertRedirect(route('parrainage.sessions.selection', $session))
            ->assertSessionHas('success', '2 OEV retenu(s) ; 1 placé(s) en liste d’attente (enveloppe ou quota atteint).');

        $lignes = $session->beneficiaires()->get()->keyBy('oev_id');
        $this->assertSame([BeneficiaireSession::RETENU, 40_000], [$lignes[$a->id]->statut, $lignes[$a->id]->montant_retenu]);
        $this->assertSame([BeneficiaireSession::RETENU, 100_000, null], [$lignes[$b->id]->statut, $lignes[$b->id]->montant_retenu, $lignes[$b->id]->frais_reels]);
        $this->assertSame([BeneficiaireSession::ATTENTE, 1], [$lignes[$c->id]->statut, $lignes[$c->id]->rang]);

        // Frais réels de B saisis (60 000) : 50 000 se libèrent… mais C (30 000) tient alors dans l'enveloppe
        $this->patch(route('parrainage.sessions.selection.modifier', [$session, $lignes[$b->id]]), ['frais_reels' => '60 000'])->assertSessionHas('success');
        $this->assertSame(60_000, $lignes[$b->id]->refresh()->montant_retenu);
        $this->patch(route('parrainage.sessions.selection.modifier', [$session, $lignes[$c->id]]), ['action' => 'retenir'])->assertSessionHas('success');
        $this->assertSame(BeneficiaireSession::RETENU, $lignes[$c->id]->refresh()->statut);

        // Plus de place : remonter les frais de C au-delà du reste est refusé
        $this->patch(route('parrainage.sessions.selection.modifier', [$session, $lignes[$c->id]]), ['frais_reels' => '90 000'])->assertSessionHas('error');
        $this->assertSame(30_000, $lignes[$c->id]->refresh()->montant_retenu);
    }

    public function test_les_quotas_regionaux_limitent_les_retenus(): void
    {
        $session = $this->nouvelleSession(['quotas_actifs' => true]);
        $session->quotas()->create(['region_id' => $this->region->id, 'montant' => 50_000]);
        $ici = $this->oev('ICI');
        $deuxieme = $this->oev('DEUXIEME_ICI', ['niveau_priorite' => 'faible']);
        $ailleurs = $this->oev('SANS_QUOTA', [], $this->autreRegion);

        $this->actingAs($this->central)->post(route('parrainage.sessions.selection.ajouter', $session), ['oev_ids' => [$ici->id, $deuxieme->id, $ailleurs->id]]);

        $statuts = $session->beneficiaires()->pluck('statut', 'oev_id');
        $this->assertSame(BeneficiaireSession::RETENU, $statuts[$ici->id]);
        $this->assertSame(BeneficiaireSession::ATTENTE, $statuts[$deuxieme->id], 'Quota de la région atteint');
        $this->assertSame(BeneficiaireSession::ATTENTE, $statuts[$ailleurs->id], 'Région sans quota');
    }

    public function test_tout_ajouter_prend_les_eligibles_filtres(): void
    {
        $session = $this->nouvelleSession();
        $this->oev('FILLE');
        $this->oev('GARCON', ['sexe' => 'M']);

        $this->actingAs($this->central)->post(route('parrainage.sessions.selection.ajouter', $session), ['tous' => '1', 'sexe' => 'M'])
            ->assertSessionHas('success');

        $this->assertSame(['GARCON'], $session->beneficiaires()->with('oev')->get()->pluck('oev.nom')->all());
    }

    public function test_valider_la_liste_definitive_fige_la_session_et_alimente_le_pilotage(): void
    {
        $session = $this->nouvelleSession();
        $oev = $this->oev('RETENU');

        $this->actingAs($this->central)->post(route('parrainage.sessions.selection.valider', $session))
            ->assertSessionHas('error', 'La session ne peut pas être validée : aucun OEV n’est retenu sur sa liste (onglet « Sélection »).');

        $this->post(route('parrainage.sessions.selection.ajouter', $session), ['oev_ids' => [$oev->id]]);
        $source = app(SourceSelection::class);
        $this->assertNull($source->montantEngage($session), 'Liste pas encore définitive');

        $this->post(route('parrainage.sessions.selection.valider', $session))->assertSessionHas('success');
        $session->refresh();
        $this->assertSame(SessionParrainage::ETAT_VALIDEE, $session->etat);
        $this->assertSame(40_000, $source->montantEngage($session));
        $this->assertSame([$oev->id], $source->listeDefinitive($session)->pluck('oev_id')->all());
        $this->assertSame(['scolaire'], $source->naturesAppuiEtat($oev->id, '2026-2027'));

        // Liste figée
        $ligne = $session->beneficiaires()->first();
        $this->delete(route('parrainage.sessions.selection.retirer', [$session, $ligne]))->assertSessionHas('error');
        $this->assertDatabaseHas('beneficiaires_session', ['id' => $ligne->id]);
    }

    public function test_les_ecrans_s_affichent_et_le_dp_consulte_sans_modifier(): void
    {
        $session = $this->nouvelleSession();
        $oev = $this->oev('KABORE', ['niveau_priorite' => 'eleve']);

        // À l'entrée, tous les éligibles sont affichés sans filtre
        $this->actingAs($this->central)->get(route('parrainage.sessions.selection', $session))
            ->assertOk()->assertSee('KABORE')->assertSee('Ajouter à la session')->assertDontSee('Urgence')
            // Les éligibles arrivent cochés
            ->assertSee('data-case-oev data-montant="40000" checked', false);
        $this->post(route('parrainage.sessions.selection.ajouter', $session), ['oev_ids' => [$oev->id]]);
        $this->get(route('parrainage.sessions.selection', ['session' => $session, 'onglet' => 'liste']))
            ->assertOk()->assertSee('KABORE')->assertSee('Valider la liste définitive');

        $this->actingAs($this->dp)->get(route('parrainage.sessions.selection', $session))
            ->assertOk()->assertSee('KABORE')->assertDontSee('Valider la liste définitive');
        $this->actingAs($this->dp)->post(route('parrainage.sessions.selection.ajouter', $session), ['oev_ids' => [$oev->id]])->assertAccesRefuse();
    }
}
