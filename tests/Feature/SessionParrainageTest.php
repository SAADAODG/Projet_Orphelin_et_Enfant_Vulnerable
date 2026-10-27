<?php

namespace Tests\Feature;

use App\Models\BeneficiaireSession;
use App\Models\JournalActivite;
use App\Models\Oev;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Services\Parrainage\ParrainageService;
use App\Services\Parrainage\TransitionSessionInvalide;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionParrainageTest extends TestCase
{
    use RefreshDatabase;

    private User $central;
    private User $admin;
    private User $dr;
    private User $dp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class]);

        $region = Region::has('provinces')->firstOrFail();
        $province = $region->provinces()->firstOrFail();
        $this->central = User::factory()->create()->assignRole('agent DGFE');
        $this->admin = User::factory()->create()->assignRole('administrateur');
        $this->dr = User::factory()->create(['region_id' => $region->id])->assignRole('DR');
        $this->dp = User::factory()->create(['region_id' => $region->id, 'province_id' => $province->id])->assignRole('DP');
    }

    private function donnees(array $surcharge = []): array
    {
        return $surcharge + [
            'description' => 'Session principale 2026-2027',
            'annee' => '2026-2027',
            'numero' => 1,
            'type_appui' => SessionParrainage::TYPE_SCOLAIRE,
            'enveloppe' => '10 000 000',
            'plafond_beneficiaire' => '75 000',
            'date_ouverture' => '2026-10-01',
            'quotas_actifs' => '1',
            'exclure_deja_appuyes' => '0',
        ];
    }

    private function nouvelleSession(array $surcharge = []): SessionParrainage
    {
        return SessionParrainage::create($surcharge + [
            'description' => 'Session principale',
            'annee' => '2026-2027',
            'numero' => 1,
            'type_appui' => SessionParrainage::TYPE_SCOLAIRE,
            'enveloppe' => 1_000_000,
            'plafond_beneficiaire' => 50_000,
            'date_ouverture' => '2026-10-01',
            'quotas_actifs' => true,
            'etat' => SessionParrainage::ETAT_EN_COURS,
        ]);
    }

    /** OEV intégré et scolarisé de la première région (éligible à une session scolaire). */
    private function oevScolarise(?Region $region = null, int $i = 0): Oev
    {
        $region ??= Region::has('provinces')->firstOrFail();

        return Oev::create([
            'nom' => 'ENFANT', 'prenom' => "Test {$i}", 'sexe' => 'F', 'date_naissance' => '2015-03-01',
            'statut' => 'vulnerable', 'handicap' => false, 'systeme_educatif' => 'classique', 'situation_scolaire' => 'scolarise',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_actuel' => 'École A', 'type_etablissement' => 'public', 'classe' => 'CM1', 'frais_scolarite' => 0,
            'region_id' => $region->id, 'province_id' => $region->provinces()->value('id'),
            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
            'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
        ]);
    }

    /** Un OEV retenu sur la liste de la session : condition pour la valider. */
    private function avecUnRetenu(SessionParrainage $session): SessionParrainage
    {
        $oev = $this->oevScolarise();
        $session->beneficiaires()->create([
            'oev_id' => $oev->id, 'type_appui' => SessionParrainage::TYPE_SCOLAIRE, 'statut' => BeneficiaireSession::RETENU,
            'region_id' => $oev->region_id, 'montant_retenu' => 0,
        ]);

        return $session;
    }

    public function test_le_niveau_central_ouvre_une_session_tracee_dans_le_journal(): void
    {
        $reponse = $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees())
            ->assertSessionHasNoErrors();

        $session = SessionParrainage::sole();
        // Une fois créée, on enchaîne sur le peuplement de la session
        $reponse->assertRedirect(route('parrainage.sessions.selection', $session));
        $this->assertSame(10_000_000, $session->enveloppe);
        $this->assertSame(75_000, $session->plafond_beneficiaire);
        $this->assertSame(SessionParrainage::ETAT_EN_COURS, $session->etat);
        $this->assertTrue($session->quotas_actifs);
        $this->assertFalse($session->exclure_deja_appuyes);
        $this->assertSame($this->central->id, $session->created_by);

        $this->assertDatabaseHas('journal_activites', [
            'action' => 'session_parrainage.creation',
            'sujet_id' => $session->id,
            'user_id' => $this->central->id,
        ]);
    }

    public function test_les_ecrans_de_la_session_s_affichent(): void
    {
        $session = $this->nouvelleSession();

        $this->actingAs($this->central)->get(route('parrainage.sessions.index'))->assertOk()->assertSee('Nouvelle session');
        // La création se fait dans une fenêtre de la liste ; plus de source de financement ni de contributions
        $this->actingAs($this->central)->get(route('parrainage.sessions.create'))->assertRedirect(route('parrainage.sessions.index', ['nouveau' => 1]));
        $this->actingAs($this->central)->get(route('parrainage.sessions.index', ['nouveau' => 1]))
            ->assertOk()->assertSee('nouvelleSessionModal')->assertDontSee('Source de financement')->assertDontSee('Bloquer le dépassement');
        $this->actingAs($this->central)->get(route('parrainage.sessions.edit', $session))->assertOk();
        $this->actingAs($this->central)->get(route('parrainage.sessions.show', $session))
            ->assertOk()
            // La validation passe par la liste de l'onglet « Sélection »
            ->assertSee('Sélectionner les OEV et valider la liste')
            ->assertSee("1\u{00A0}000\u{00A0}000\u{00A0}FCFA", false);
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['enveloppe' => '0']))
            ->assertSessionHasErrors('enveloppe');
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['annee' => '2026-2028']))
            ->assertSessionHasErrors('annee');

        $this->assertDatabaseCount('sessions_parrainage', 1);
    }

    public function test_l_enveloppe_est_facultative_et_se_renseigne_plus_tard(): void
    {
        // Sans enveloppe : session enregistrée, mais pas de quotas par région (ils répartissent l'enveloppe)
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['enveloppe' => '', 'quotas_actifs' => '1']))
            ->assertSessionHasErrors('enveloppe');
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['enveloppe' => '', 'quotas_actifs' => '0']))
            ->assertSessionHasNoErrors();
        $session = SessionParrainage::sole();
        $this->assertNull($session->enveloppe);
        $this->assertFalse($session->aUneEnveloppe());
        $this->actingAs($this->central)->get(route('parrainage.sessions.show', $session))->assertOk()->assertSee('Non renseignée');

        // Liste validée : l'enveloppe se renseigne encore, sans descendre sous le montant engagé
        $oev = Oev::create([
            'nom' => 'ENFANT', 'prenom' => 'Test', 'sexe' => 'F', 'date_naissance' => '2015-01-01', 'statut' => 'vulnerable',
            'handicap' => false, 'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'systeme_educatif' => 'classique', 'statut_dossier' => Oev::ETAT_INTEGRE, 'numero_dossier' => Oev::genererNumeroDossier(),
        ]);
        $session->beneficiaires()->create(['oev_id' => $oev->id, 'type_appui' => SessionParrainage::TYPE_SCOLAIRE, 'statut' => BeneficiaireSession::RETENU,
            'score' => 10, 'frais_reels' => 60_000, 'frais_estimes' => false, 'montant_retenu' => 60_000]);
        $session->update(['etat' => SessionParrainage::ETAT_VALIDEE]);
        $this->actingAs($this->central)->put(route('parrainage.sessions.update', $session), ['description' => $session->description, 'enveloppe' => '50 000'])
            ->assertSessionHasErrors('enveloppe');
        $this->actingAs($this->central)->put(route('parrainage.sessions.update', $session), ['description' => $session->description, 'enveloppe' => '80 000'])
            ->assertSessionHasNoErrors();
        $this->assertSame(80_000, $session->fresh()->enveloppe);
    }

    public function test_les_dr_et_dp_consultent_sans_pouvoir_creer_ni_modifier(): void
    {
        $session = $this->nouvelleSession();

        foreach ([$this->dr, $this->dp] as $lecteur) {
            $this->actingAs($lecteur)->get(route('parrainage.sessions.index'))->assertOk()->assertSee('Session principale');
            $this->actingAs($lecteur)->get(route('parrainage.sessions.show', $session))->assertOk()->assertDontSee('valider la liste');
            // Accès refusé : redirection avec message d'erreur (convention du projet), rien n'est enregistré
            $this->actingAs($lecteur)->get(route('parrainage.sessions.create'))->assertRedirect();
            $this->actingAs($lecteur)->post(route('parrainage.sessions.store'), $this->donnees(['numero' => 5]))->assertRedirect();
            $this->actingAs($lecteur)->put(route('parrainage.sessions.update', $session), $this->donnees(['description' => 'Modifiée']))->assertRedirect();
        }

        $this->assertDatabaseCount('sessions_parrainage', 1);
        $this->assertSame('Session principale', $session->refresh()->description);
    }

    public function test_la_fiche_de_la_session_liste_les_oev_retenus(): void
    {
        $session = $this->avecUnRetenu($this->nouvelleSession());

        $this->actingAs($this->central)->get(route('parrainage.sessions.show', $session))
            ->assertOk()->assertSee('OEV retenus')->assertSee('ENFANT Test 0')->assertSee('Ajouter des OEV');
        $this->actingAs($this->dp)->get(route('parrainage.sessions.show', $session))
            ->assertOk()->assertSee('ENFANT Test 0')->assertDontSee('Ajouter des OEV');
    }

    public function test_une_session_peut_etre_supprimee_avec_sa_selection(): void
    {
        $session = $this->avecUnRetenu($this->nouvelleSession());
        $session->quotas()->create(['region_id' => Region::value('id'), 'montant' => 100_000]);

        $this->actingAs($this->dp)->delete(route('parrainage.sessions.destroy', $session))->assertRedirect();
        $this->assertModelExists($session);

        $this->actingAs($this->central)->delete(route('parrainage.sessions.destroy', $session))
            ->assertRedirect(route('parrainage.sessions.index'))->assertSessionHas('success');
        $this->assertModelMissing($session);
        $this->assertDatabaseCount('beneficiaires_session', 0);
        $this->assertDatabaseCount('quotas_regionaux', 0);
        $this->assertDatabaseHas('journal_activites', ['action' => 'session_parrainage.suppression', 'sujet_id' => $session->id]);

        // Paiement lancé : seul un administrateur peut supprimer
        $enPaiement = $this->nouvelleSession(['numero' => 2, 'etat' => SessionParrainage::ETAT_PAIEMENT]);
        $this->actingAs($this->central)->delete(route('parrainage.sessions.destroy', $enPaiement))->assertSessionHas('error');
        $this->assertModelExists($enPaiement);
        $this->actingAs($this->admin)->delete(route('parrainage.sessions.destroy', $enPaiement))->assertSessionHas('success');
        $this->assertModelMissing($enPaiement);
    }

    public function test_la_somme_des_quotas_ne_depasse_pas_l_enveloppe(): void
    {
        $session = $this->nouvelleSession();
        [$r1, $r2] = Region::orderBy('nom')->take(2)->pluck('id')->all();

        $this->actingAs($this->central)->put(route('parrainage.sessions.quotas', $session), ['quotas' => [$r1 => '600 000', $r2 => '500 000']])
            ->assertSessionHasErrors('quotas');
        $this->assertSame(0, $session->quotas()->count());

        $this->actingAs($this->central)->put(route('parrainage.sessions.quotas', $session), ['quotas' => [$r1 => '600 000', $r2 => '400 000']])
            ->assertSessionHasNoErrors();
        $this->assertSame([$r1 => 600_000, $r2 => 400_000], $session->quotas()->pluck('montant', 'region_id')->all());
        $this->assertSame(1_000_000, $this->app->make(ParrainageService::class)->getSessionParametres($session->id)['quotas'][$r1] + 400_000);
    }

    public function test_les_quotas_sont_refuses_si_l_option_n_est_pas_cochee(): void
    {
        $session = $this->nouvelleSession(['quotas_actifs' => false]);

        $this->actingAs($this->central)->put(route('parrainage.sessions.quotas', $session), ['quotas' => [Region::value('id') => '1 000']])
            ->assertSessionHas('error');
        $this->assertSame(0, $session->quotas()->count());
    }

    public function test_la_proposition_au_prorata_repartit_toute_l_enveloppe(): void
    {
        $session = $this->nouvelleSession(['enveloppe' => 1_000_001]);
        [$r1, $r2] = Region::orderBy('nom')->take(2)->get()->all();
        foreach ([[$r1, 2], [$r2, 1]] as [$region, $nombre]) {
            for ($i = 0; $i < $nombre; $i++) {
                $this->oevScolarise($region, $i);
            }
        }
        // Session scolaire : un OEV en formation (non scolarisé) n'est pas éligible et ne pèse pas dans le prorata
        $this->oevScolarise($r2, 9)->update(['situation_scolaire' => 'non_scolarise', 'formation_professionnelle' => true, 'formation_etat' => 'en_cours']);

        $quotas = $this->app->make(ParrainageService::class)->proposerQuotasAuProrata($session);

        $this->assertSame(1_000_001, array_sum($quotas));
        $this->assertEqualsWithDelta(666_667, $quotas[$r1->id], 1);
        $this->assertEqualsWithDelta(333_334, $quotas[$r2->id], 1);

        // Proposition affichée, non enregistrée
        $this->actingAs($this->central)->get(route('parrainage.sessions.show', ['session' => $session, 'vue' => 'quotas', 'prorata' => 1]))
            ->assertOk()->assertSee('Proposition calculée');
        $this->assertSame(0, $session->quotas()->count());
    }

    public function test_les_transitions_avancent_une_etape_a_la_fois(): void
    {
        $session = $this->nouvelleSession();

        // Pas de saut d'étape
        $this->actingAs($this->central)->post(route('parrainage.sessions.etat', $session), ['etat' => SessionParrainage::ETAT_PAIEMENT])
            ->assertSessionHasErrors('etat');
        // Pas de validation sans OEV retenu sur la liste
        $this->actingAs($this->central)->post(route('parrainage.sessions.etat', $session), ['etat' => SessionParrainage::ETAT_VALIDEE])
            ->assertSessionHasErrors(['etat' => 'La session ne peut pas être validée : aucun OEV n’est retenu sur sa liste (onglet « Sélection »).']);
        $this->avecUnRetenu($session);

        foreach ([SessionParrainage::ETAT_VALIDEE, SessionParrainage::ETAT_PAIEMENT, SessionParrainage::ETAT_CLOTUREE] as $etat) {
            $this->actingAs($this->central)->post(route('parrainage.sessions.etat', $session), ['etat' => $etat])
                ->assertSessionHasNoErrors();
            $this->assertSame($etat, $session->refresh()->etat);
        }

        $this->assertTrue($session->date_cloture->isToday());
        $this->assertSame(3, JournalActivite::de($session)->where('action', 'session_parrainage.changement_etat')->count());
    }

    public function test_le_dr_ne_change_pas_l_etat(): void
    {
        $session = $this->nouvelleSession();

        $this->actingAs($this->dr)->post(route('parrainage.sessions.etat', $session), ['etat' => SessionParrainage::ETAT_VALIDEE])
            ->assertSessionHasErrors('etat');
        $this->assertSame(SessionParrainage::ETAT_EN_COURS, $session->refresh()->etat);
    }

    public function test_seul_un_administrateur_revient_en_arriere_avec_un_motif(): void
    {
        $session = $this->nouvelleSession(['etat' => SessionParrainage::ETAT_CLOTUREE, 'date_cloture' => '2027-07-01']);

        $this->actingAs($this->central)->post(route('parrainage.sessions.etat', $session), ['etat' => SessionParrainage::ETAT_PAIEMENT, 'motif' => 'Erreur'])
            ->assertSessionHasErrors('etat');
        $this->actingAs($this->admin)->post(route('parrainage.sessions.etat', $session), ['etat' => SessionParrainage::ETAT_PAIEMENT])
            ->assertSessionHasErrors('etat');
        $this->assertSame(SessionParrainage::ETAT_CLOTUREE, $session->refresh()->etat);

        $this->actingAs($this->admin)->post(route('parrainage.sessions.etat', $session), ['etat' => SessionParrainage::ETAT_PAIEMENT, 'motif' => 'Virement rejeté par la banque'])
            ->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame(SessionParrainage::ETAT_PAIEMENT, $session->etat);
        $this->assertNull($session->date_cloture);
        $this->assertSame('Virement rejeté par la banque', JournalActivite::de($session)->first()->details['motif']);
    }

    public function test_une_session_validee_fige_ses_parametres_et_une_session_cloturee_est_en_lecture_seule(): void
    {
        $session = $this->nouvelleSession(['etat' => SessionParrainage::ETAT_VALIDEE]);

        $this->actingAs($this->central)->put(route('parrainage.sessions.update', $session), $this->donnees(['description' => 'Nouvelle description', 'enveloppe' => '99 000 000']))
            ->assertSessionHasNoErrors();
        $session->refresh();
        $this->assertSame('Nouvelle description', $session->description);
        // L'enveloppe peut encore être renseignée ou corrigée ; le plafond et les options restent figés
        $this->assertSame(99_000_000, $session->enveloppe);
        $this->assertSame(50_000, $session->plafond_beneficiaire);

        $this->actingAs($this->central)->put(route('parrainage.sessions.quotas', $session), ['quotas' => [Region::value('id') => '1 000']])
            ->assertSessionHas('error');

        $session->update(['etat' => SessionParrainage::ETAT_CLOTUREE]);
        $this->actingAs($this->central)->put(route('parrainage.sessions.update', $session), $this->donnees(['description' => 'Interdit']))
            ->assertSessionHas('error');
        $this->assertSame('Nouvelle description', $session->refresh()->description);
    }

    public function test_le_service_expose_les_parametres_et_controle_les_transitions(): void
    {
        $session = $this->nouvelleSession(['quotas_actifs' => false, 'exclure_deja_appuyes' => true]);
        $service = $this->app->make(ParrainageService::class);

        $parametres = $service->getSessionParametres($session->id);
        $this->assertSame(1_000_000, $parametres['enveloppe']);
        $this->assertSame(50_000, $parametres['plafond_beneficiaire']);
        $this->assertTrue($parametres['exclure_deja_appuyes']);
        $this->assertSame([], $parametres['quotas']);

        $this->avecUnRetenu($session);
        $this->assertSame(SessionParrainage::ETAT_VALIDEE, $service->changerEtatSession($session->id, SessionParrainage::ETAT_VALIDEE, $this->central)->etat);

        $this->expectException(TransitionSessionInvalide::class);
        $service->changerEtatSession($session->id, SessionParrainage::ETAT_CLOTUREE, $this->central);
    }
}
