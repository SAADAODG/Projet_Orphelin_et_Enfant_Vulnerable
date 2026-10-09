<?php

namespace Tests\Feature;

use App\Models\JournalActivite;
use App\Models\Oev;
use App\Models\Parrain;
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
            'source_financement' => SessionParrainage::SOURCE_ETAT,
            'date_ouverture' => '2026-10-01',
            'bloquer_depassement_enveloppe' => '1',
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
            'source_financement' => SessionParrainage::SOURCE_ETAT,
            'date_ouverture' => '2026-10-01',
            'quotas_actifs' => true,
            'etat' => SessionParrainage::ETAT_EN_COURS,
        ]);
    }

    public function test_le_niveau_central_ouvre_une_session_tracee_dans_le_journal(): void
    {
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $session = SessionParrainage::sole();
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
        $parrain = Parrain::create(['type' => 'entreprise', 'nom' => 'Société Solidaire']);
        $session = $this->nouvelleSession(['source_financement' => SessionParrainage::SOURCE_MIXTE]);
        $session->parrains()->attach($parrain, ['montant' => 300_000]);

        $this->actingAs($this->central)->get(route('parrainage.sessions.index'))->assertOk()->assertSee('Nouvelle session');
        $this->actingAs($this->central)->get(route('parrainage.sessions.create'))->assertOk()->assertSee('Société Solidaire');
        $this->actingAs($this->central)->get(route('parrainage.sessions.edit', $session))->assertOk();
        $this->actingAs($this->central)->get(route('parrainage.sessions.show', $session))
            ->assertOk()
            ->assertSee('Valider la session')
            ->assertSee("1\u{00A0}000\u{00A0}000\u{00A0}FCFA", false)
            ->assertSee('Société Solidaire');
        $this->actingAs($this->central)->get(route('parrainage.sessions.show', ['session' => $session, 'vue' => 'quotas']))
            ->assertOk()->assertSee('Reste à répartir');
    }

    public function test_le_couple_annee_numero_est_unique(): void
    {
        $this->nouvelleSession();

        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees())
            ->assertSessionHasErrors('numero');

        // Même numéro une autre année, ou autre numéro la même année : accepté
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['annee' => '2027-2028']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['numero' => 2]))
            ->assertSessionHasNoErrors();
    }

    public function test_le_plafond_ne_depasse_pas_l_enveloppe_et_l_enveloppe_est_positive(): void
    {
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['plafond_beneficiaire' => '20 000 000']))
            ->assertSessionHasErrors('plafond_beneficiaire');
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['enveloppe' => '0']))
            ->assertSessionHasErrors('enveloppe');
        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['annee' => '2026-2028']))
            ->assertSessionHasErrors('annee');

        $this->assertDatabaseCount('sessions_parrainage', 0);
    }

    public function test_un_financement_partenaire_exige_au_moins_une_contribution(): void
    {
        $parrain = Parrain::create(['type' => 'ong', 'nom' => 'ONG Espoir']);

        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees(['source_financement' => SessionParrainage::SOURCE_PARTENAIRE]))
            ->assertSessionHasErrors('contributions');

        $this->actingAs($this->central)->post(route('parrainage.sessions.store'), $this->donnees([
            'source_financement' => SessionParrainage::SOURCE_MIXTE,
            'contributions' => [$parrain->id => '2 500 000'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2_500_000, SessionParrainage::sole()->parrains->sole()->pivot->montant);
    }

    public function test_les_dr_et_dp_consultent_sans_pouvoir_creer_ni_modifier(): void
    {
        $session = $this->nouvelleSession();

        foreach ([$this->dr, $this->dp] as $lecteur) {
            $this->actingAs($lecteur)->get(route('parrainage.sessions.index'))->assertOk()->assertSee('Session principale');
            $this->actingAs($lecteur)->get(route('parrainage.sessions.show', $session))->assertOk()->assertDontSee('Valider la session');
            // Accès refusé : redirection avec message d'erreur (convention du projet), rien n'est enregistré
            $this->actingAs($lecteur)->get(route('parrainage.sessions.create'))->assertRedirect();
            $this->actingAs($lecteur)->post(route('parrainage.sessions.store'), $this->donnees(['numero' => 5]))->assertRedirect();
            $this->actingAs($lecteur)->put(route('parrainage.sessions.update', $session), $this->donnees(['description' => 'Modifiée']))->assertRedirect();
        }

        $this->assertDatabaseCount('sessions_parrainage', 1);
        $this->assertSame('Session principale', $session->refresh()->description);
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
                Oev::create([
                    'nom' => 'ENFANT', 'prenom' => "Test {$i}", 'sexe' => 'F', 'date_naissance' => '2015-03-01',
                    'statut' => 'vulnerable', 'handicap' => false, 'systeme_educatif' => 'classique',
                    'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
                    'etablissement_actuel' => 'École A', 'type_etablissement' => 'public', 'classe' => 'CM1', 'frais_scolarite' => 0,
                    'region_id' => $region->id, 'province_id' => $region->provinces()->value('id'),
                    'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
                    'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
                ]);
            }
        }

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
        $this->assertSame(1_000_000, $session->enveloppe);

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

        $this->assertSame(SessionParrainage::ETAT_VALIDEE, $service->changerEtatSession($session->id, SessionParrainage::ETAT_VALIDEE, $this->central)->etat);

        $this->expectException(TransitionSessionInvalide::class);
        $service->changerEtatSession($session->id, SessionParrainage::ETAT_CLOTUREE, $this->central);
    }
}
