<?php

namespace Tests\Feature;

use App\Models\BeneficiaireSession;
use App\Models\Oev;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\SuiviParrainage;
use App\Models\User;
use App\Services\Parrainage\ParcoursScolaire;
use App\Services\Parrainage\SelectionParrainage;
use App\Services\Parrainage\SourceSelection;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuiviParrainageTest extends TestCase
{
    use RefreshDatabase;

    private User $central;
    private User $dp;
    private User $dr;
    private Region $region;
    private Region $autreRegion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class]);

        [$this->region, $this->autreRegion] = Region::has('provinces')->orderBy('id')->take(2)->get()->all();
        $this->central = User::factory()->create()->assignRole('agent DGFE');
        $this->dp = User::factory()->create(['region_id' => $this->region->id, 'province_id' => $this->region->provinces()->value('id')])->assignRole('DP');
        $this->dr = User::factory()->create(['region_id' => $this->region->id])->assignRole('DR');
    }

    private function nouvelleSession(array $surcharge = []): SessionParrainage
    {
        return SessionParrainage::create($surcharge + [
            'description' => 'Session principale', 'annee' => '2026-2027', 'numero' => 1,
            'type_appui' => SessionParrainage::TYPE_LES_DEUX, 'enveloppe' => 1_000_000, 'plafond_beneficiaire' => 100_000,
            'source_financement' => SessionParrainage::SOURCE_ETAT, 'date_ouverture' => '2026-10-01',
            'etat' => SessionParrainage::ETAT_VALIDEE,
        ]);
    }

    private function retenu(SessionParrainage $session, string $nom, array $champs = [], ?Region $region = null, string $type = SessionParrainage::TYPE_SCOLAIRE): BeneficiaireSession
    {
        $region ??= $this->region;
        $oev = Oev::create($champs + [
            'nom' => $nom, 'prenom' => 'Test', 'sexe' => 'F', 'date_naissance' => now()->subYears(10)->toDateString(),
            'statut' => 'vulnerable', 'handicap' => false, 'situation_scolaire' => 'scolarise', 'classe' => '5e',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'region_id' => $region->id, 'province_id' => $region->provinces()->value('id'),
            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
            'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
        ]);

        return $session->beneficiaires()->create([
            'oev_id' => $oev->id, 'type_appui' => $type, 'statut' => BeneficiaireSession::RETENU,
            'region_id' => $oev->region_id, 'montant_retenu' => 40_000,
        ]);
    }

    public function test_les_parraines_des_sessions_validees_sont_a_suivre(): void
    {
        $session = $this->nouvelleSession();
        $this->retenu($session, 'PARRAINE');
        $this->retenu($this->nouvelleSession(['numero' => 2, 'etat' => SessionParrainage::ETAT_EN_COURS]), 'PAS_ENCORE_PARRAINE');

        $this->actingAs($this->central)->get(route('parrainage.suivis.index'))
            ->assertOk()->assertSee('PARRAINE')->assertDontSee('PAS_ENCORE_PARRAINE')->assertSee('À renseigner')->assertSee('Renseigner');
    }

    public function test_le_dp_renseigne_un_suivi_scolaire_de_sa_province(): void
    {
        $session = $this->nouvelleSession();
        $ligne = $this->retenu($session, 'ELEVE');
        $ailleurs = $this->retenu($session, 'AILLEURS', [], $this->autreRegion);

        $this->actingAs($this->dp)->get(route('parrainage.suivis.index'))->assertOk()->assertSee('ELEVE')->assertDontSee('AILLEURS');

        // Moyenne sur 20 en 5e : 25 est refusé ; l'année prochaine est obligatoire
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['ligne_id' => $ligne->id, 'resultat' => 'admis', 'moyenne' => '25', 'a_reconduire' => '1'])
            ->assertSessionHasErrors(['moyenne', 'orientation']);

        // Admis, continue l'école : la classe (4e) est calculée, pas saisie
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['ligne_id' => $ligne->id, 'resultat' => 'admis', 'moyenne' => '13,5', 'orientation' => 'ecole', 'prochaine_classe' => 'CM1', 'prochains_frais' => '40 000', 'a_reconduire' => '1', 'observation' => 'Très assidue'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $suivi = $ligne->suivi()->sole();
        $this->assertSame(['admis', '13.50', 'ecole', '4e', true, '2026-2027', $this->dp->id], [$suivi->resultat, $suivi->moyenne, $suivi->orientation, $suivi->prochaine_annee, $suivi->a_reconduire, $suivi->annee, $suivi->renseigne_par]);
        $this->assertSame('Passe en 4e', $suivi->libelleProchaineAnnee());

        // Redouble : même classe, calculée
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['ligne_id' => $ligne->id, 'resultat' => 'redouble', 'orientation' => 'ecole', 'prochains_frais' => '40 000', 'a_reconduire' => '1']);
        $this->assertSame(['redouble', '5e', 'Redouble la 5e'], [$ligne->suivi()->sole()->resultat, $ligne->suivi()->sole()->prochaine_annee, $ligne->suivi()->sole()->libelleProchaineAnnee()]);
        $this->assertSame(1, SuiviParrainage::count());

        // Admis mais quitte l'école pour une formation professionnelle : la formation est demandée
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['ligne_id' => $ligne->id, 'resultat' => 'admis', 'orientation' => 'formation', 'a_reconduire' => '1'])
            ->assertSessionHasErrors('prochaine_filiere');
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['ligne_id' => $ligne->id, 'resultat' => 'admis', 'orientation' => 'formation', 'prochaine_filiere' => 'Couture', 'prochain_type_centre' => 'prive', 'prochaine_duree' => '24', 'prochains_frais_formation' => '60 000', 'a_reconduire' => '1']);
        $suivi = $ligne->suivi()->sole();
        $this->assertSame(['admis', 'formation', 'Couture', 'Formation professionnelle : Couture'], [$suivi->resultat, $suivi->orientation, $suivi->prochaine_annee, $suivi->libelleProchaineAnnee()]);

        // Arrêt : rien de prévu l'an prochain
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['ligne_id' => $ligne->id, 'resultat' => 'abandon', 'orientation' => 'arret', 'prochaine_filiere' => 'Couture', 'a_reconduire' => '0']);
        $this->assertSame([null, 'Arrête l’école'], [$ligne->suivi()->sole()->prochaine_annee, $ligne->suivi()->sole()->libelleProchaineAnnee()]);
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['ligne_id' => $ligne->id, 'resultat' => 'admis', 'orientation' => 'ecole', 'prochains_frais' => '40 000', 'a_reconduire' => '1']);

        // Les cartes de compteurs affichent la liste correspondante
        $this->get(route('parrainage.suivis.index', ['etat' => 'renseigne']))->assertOk()->assertSee('ELEVE')->assertSee('L’an prochain');
        $this->get(route('parrainage.suivis.index', ['etat' => 'a_renseigner']))->assertOk()->assertDontSee('ELEVE');

        // Hors de sa province : refusé
        $this->put(route('parrainage.suivis.enregistrer', $ailleurs), ['resultat' => 'admis', 'a_reconduire' => '1'])->assertAccesRefuse();
        $this->assertNull($ailleurs->suivi()->first());
    }

    public function test_le_suivi_d_une_formation_demande_l_insertion_si_elle_est_achevee(): void
    {
        $ligne = $this->retenu($this->nouvelleSession(), 'APPRENTI', ['situation_scolaire' => 'descolarise', 'classe' => null, 'formation_professionnelle' => true, 'formation_etat' => 'en_cours', 'formation_filiere' => 'Couture'], null, SessionParrainage::TYPE_FORMATION);

        $this->actingAs($this->central)->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'admis', 'a_reconduire' => '1'])
            ->assertSessionHasErrors('resultat');
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'achevee', 'a_reconduire' => '0'])
            ->assertSessionHasErrors('insertion');
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'achevee', 'insertion' => 'auto_emploi', 'a_reconduire' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['achevee', 'auto_emploi', null, null, false], [$ligne->suivi->resultat, $ligne->suivi->insertion, $ligne->suivi->orientation, $ligne->suivi->prochaine_annee, $ligne->suivi->a_reconduire]);

        // Poursuit : l'année prochaine est déduite (même filière)
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'poursuit', 'prochains_frais' => '50 000', 'a_reconduire' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(['formation', 'Couture'], [$ligne->suivi()->sole()->orientation, $ligne->suivi()->sole()->prochaine_annee]);
    }

    public function test_le_suivi_met_a_jour_la_fiche_de_l_oev_sans_double_passage_de_classe(): void
    {
        $ligne = $this->retenu($this->nouvelleSession(), 'ELEVE', ['niveau_etude' => 'post_primaire_secondaire', 'etablissement_actuel' => 'Lycée de la Paix', 'type_etablissement' => 'public', 'systeme_educatif' => 'classique', 'frais_scolarite' => 35_000]);
        $oev = $ligne->oev;
        $enregistrer = fn (array $donnees) => $this->put(route('parrainage.suivis.enregistrer', $ligne), $donnees + ['a_reconduire' => '1', 'prochains_frais' => '45 000', 'prochain_type_centre' => 'prive', 'prochaine_duree' => '24', 'prochains_frais_formation' => '60 000'])->assertSessionHasNoErrors();
        $this->actingAs($this->central);

        // Admis, continue l'école : la fiche passe en 4e, la 5e devient l'année précédente
        $enregistrer(['resultat' => 'admis', 'moyenne' => '12,5', 'orientation' => 'ecole'])->assertSessionHas('success', fn ($m) => str_contains($m, 'Fiche de l’OEV mise à jour : Passe en 4e'));
        $oev->refresh();
        $this->assertSame(['scolarise', '4e', 'post_primaire_secondaire', '5e', 'Lycée de la Paix', 'admis', '12.50', 45_000],
            [$oev->situation_scolaire, $oev->classe, $oev->niveau_etude, $oev->classe_precedente, $oev->etablissement_precedent, $oev->appreciation, $oev->moyenne_annuelle, $oev->frais_scolarite]);

        // Correction (redouble) : on repart de la 5e, pas de la 4e
        $enregistrer(['resultat' => 'redouble', 'orientation' => 'ecole']);
        $this->assertSame(['5e', 'redouble'], [$oev->refresh()->classe, $oev->appreciation]);
        $this->assertSame('5e', $ligne->suivi()->sole()->fiche_avant['classe']);

        // Admis mais part en formation : déscolarisé, en formation professionnelle (cohérent avec le formulaire)
        $enregistrer(['resultat' => 'admis', 'orientation' => 'formation', 'prochaine_filiere' => 'Couture']);
        $oev->refresh();
        $this->assertSame(['descolarise', null, null, 'autre', true, 'en_cours', 'Couture', '5e'],
            [$oev->situation_scolaire, $oev->classe, $oev->etablissement_actuel, $oev->raison_non_scolarisation, $oev->formation_professionnelle, $oev->formation_etat, $oev->formation_filiere, $oev->classe_precedente]);
        // Centre, durée et frais de la formation saisis au suivi
        $this->assertSame(['prive', 24, 60_000, null], [$oev->formation_type_centre, $oev->formation_duree_mois, $oev->frais_formation, $oev->frais_scolarite]);
        // À la session suivante, il relève de la formation professionnelle, avec ses frais réels
        $suivante = $this->nouvelleSession(['annee' => '2027-2028', 'etat' => SessionParrainage::ETAT_EN_COURS]);
        $this->assertSame(SessionParrainage::TYPE_FORMATION, SelectionParrainage::typePour($oev, $suivante));
        $this->assertSame(['frais' => 60_000, 'estime' => false], SelectionParrainage::fraisPour($oev, $suivante));

        // Nouvelle correction : retour à l'école, la formation disparaît de la fiche
        $enregistrer(['resultat' => 'admis', 'orientation' => 'ecole']);
        $oev->refresh();
        $this->assertSame(['scolarise', '4e', 'Lycée de la Paix', null], [$oev->situation_scolaire, $oev->classe, $oev->etablissement_actuel, $oev->formation_etat]);

        // Le suivi d'une année passée ne modifie plus la fiche quand un suivi plus récent existe
        $ligneSuivante = $suivante->beneficiaires()->create(['oev_id' => $oev->id, 'type_appui' => 'scolaire', 'statut' => BeneficiaireSession::RETENU, 'region_id' => $oev->region_id, 'montant_retenu' => 1]);
        $suivante->update(['etat' => SessionParrainage::ETAT_VALIDEE]);
        $this->put(route('parrainage.suivis.enregistrer', $ligneSuivante), ['resultat' => 'admis', 'orientation' => 'ecole', 'prochains_frais' => '50 000', 'a_reconduire' => '1']);
        $this->assertSame('3e', $oev->refresh()->classe);
        $enregistrer(['resultat' => 'redouble', 'orientation' => 'ecole'])->assertSessionHas('success', fn ($m) => str_contains($m, 'n’est pas modifiée'));
        $this->assertSame('3e', $oev->refresh()->classe);
    }

    public function test_tout_le_parcours_scolaire_reste_visible_annee_par_annee(): void
    {
        // En 5e au Lycée de la Paix (35 000), après une 6e réussie au CEG de Koupéla
        $ligne = $this->retenu($this->nouvelleSession(), 'ELEVE', ['niveau_etude' => 'post_primaire_secondaire', 'etablissement_actuel' => 'Lycée de la Paix', 'type_etablissement' => 'public',
            'frais_scolarite' => 35_000, 'etablissement_precedent' => 'CEG de Koupéla', 'classe_precedente' => '6e', 'appreciation' => 'admis', 'moyenne_annuelle' => 11]);
        $this->actingAs($this->central)->put(route('parrainage.suivis.enregistrer', $ligne), [
            'resultat' => 'admis', 'moyenne' => '13', 'orientation' => 'ecole', 'prochain_etablissement' => 'Lycée Wend-Kuni', 'prochain_type_etablissement' => 'prive',
            'prochains_frais' => '75 000', 'a_reconduire' => '1',
        ])->assertSessionHasNoErrors();

        // Année suivante parrainée, puis départ en formation professionnelle
        $suivante = $this->nouvelleSession(['annee' => '2027-2028', 'numero' => 2]);
        $ligne2 = $suivante->beneficiaires()->create(['oev_id' => $ligne->oev_id, 'type_appui' => 'scolaire', 'statut' => BeneficiaireSession::RETENU, 'region_id' => $ligne->region_id, 'montant_retenu' => 75_000]);
        $this->put(route('parrainage.suivis.enregistrer', $ligne2), [
            'resultat' => 'admis', 'orientation' => 'formation', 'prochaine_filiere' => 'Couture', 'prochain_type_centre' => 'prive', 'prochaine_duree' => '24',
            'prochains_frais_formation' => '90 000', 'a_reconduire' => '1',
        ])->assertSessionHasNoErrors();

        $parcours = collect(app(ParcoursScolaire::class)->pour($ligne->oev->refresh()));
        $this->assertSame(['2025-2026', '2026-2027', '2027-2028', '2028-2029'], $parcours->pluck('annee')->all());
        $this->assertSame(['6e', 'CEG de Koupéla', 'Admis', '11/20'], [$parcours[0]['situation'], $parcours[0]['detail'], $parcours[0]['resultat'], $parcours[0]['moyenne']]);
        $this->assertSame(['5e', 'Lycée de la Paix · Public', 'Admis en classe supérieure', '13/20'], [$parcours[1]['situation'], $parcours[1]['detail'], $parcours[1]['resultat'], $parcours[1]['moyenne']]);
        $this->assertStringContainsString('35 000', str_replace("\u{00A0}", ' ', $parcours[1]['frais']));
        $this->assertSame(['4e', 'Lycée Wend-Kuni · Privé'], [$parcours[2]['situation'], $parcours[2]['detail']]);
        $this->assertStringContainsString('75 000', str_replace("\u{00A0}", ' ', $parcours[2]['frais']));
        $this->assertSame(['Formation professionnelle : Couture', 'Centre privé · 24 mois', 'actuelle'], [$parcours[3]['situation'], $parcours[3]['detail'], $parcours[3]['periode']]);
        $this->assertStringContainsString('90 000', str_replace("\u{00A0}", ' ', $parcours[3]['frais']));

        $this->get(route('oevs.show', $ligne->oev))->assertOk()->assertSee('Parcours scolaire et professionnel')->assertSee('CEG de Koupéla')->assertSee('Lycée Wend-Kuni');
    }

    public function test_le_suivi_d_une_formation_met_a_jour_la_fiche(): void
    {
        $ligne = $this->retenu($this->nouvelleSession(), 'APPRENTI', ['situation_scolaire' => 'descolarise', 'classe' => null, 'formation_professionnelle' => true, 'formation_etat' => 'en_cours',
            'formation_filiere' => 'Mécanique', 'formation_duree_mois' => 24, 'formation_duree_recue_mois' => 12], null, SessionParrainage::TYPE_FORMATION);
        $this->actingAs($this->central);

        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'achevee', 'insertion' => 'emploi', 'a_reconduire' => '0']);
        $this->assertSame(['achevee', 24], [$ligne->oev->refresh()->formation_etat, $ligne->oev->formation_duree_recue_mois]);

        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'abandon', 'a_reconduire' => '0']);
        $this->assertSame([false, null], [$ligne->oev->refresh()->formation_professionnelle, $ligne->oev->formation_filiere]);
        // L'historique reste dans le suivi
        $this->assertSame('Mécanique', $ligne->suivi()->sole()->fiche_avant['formation_filiere']);
    }

    public function test_un_enfant_dont_la_classe_est_inconnue_recoit_sa_classe_saisie(): void
    {
        // Enfant inscrit grâce au parrainage : pas de classe sur sa fiche
        $ligne = $this->retenu($this->nouvelleSession(), 'INSCRIT', ['situation_scolaire' => 'non_scolarise', 'classe' => null]);

        $this->actingAs($this->central)->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'admis', 'orientation' => 'ecole', 'a_reconduire' => '1'])
            ->assertSessionHasErrors('prochaine_classe');
        $this->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'admis', 'orientation' => 'ecole', 'prochaine_classe' => 'CP2', 'prochains_frais' => '0', 'a_reconduire' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('CP2', $ligne->suivi()->sole()->prochaine_annee);
    }

    public function test_le_dr_consulte_sans_renseigner_et_une_session_en_cours_n_a_pas_de_suivi(): void
    {
        $ligne = $this->retenu($this->nouvelleSession(), 'ELEVE');
        $enCours = $this->retenu($this->nouvelleSession(['numero' => 2, 'etat' => SessionParrainage::ETAT_EN_COURS]), 'EN_COURS');

        $this->actingAs($this->dr)->get(route('parrainage.suivis.index'))->assertOk()->assertSee('ELEVE')->assertDontSee('Renseigner');
        $this->actingAs($this->dr)->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'admis', 'a_reconduire' => '1'])->assertRedirect();
        $this->assertSame(0, SuiviParrainage::count());

        $this->actingAs($this->central)->put(route('parrainage.suivis.enregistrer', $enCours), ['resultat' => 'admis', 'a_reconduire' => '1'])->assertRedirect();
        $this->assertNull($enCours->suivi()->first());
    }

    public function test_le_suivi_eclaire_la_selection_suivante_et_alimente_le_pilotage(): void
    {
        $ligne = $this->retenu($this->nouvelleSession(), 'ANCIEN');
        $this->actingAs($this->central)->put(route('parrainage.suivis.enregistrer', $ligne), ['resultat' => 'admis', 'moyenne' => '14', 'orientation' => 'ecole', 'prochains_frais' => '40 000', 'a_reconduire' => '1']);

        $this->assertSame([['oev_id' => $ligne->oev_id, 'annee' => '2026-2027', 'resultat' => 'admis']], app(SourceSelection::class)->resultatsScolaires('2026-2027')->all());

        // Session de l'année suivante : le résultat s'affiche à côté de l'OEV
        $suivante = $this->nouvelleSession(['annee' => '2027-2028', 'etat' => SessionParrainage::ETAT_EN_COURS]);
        $this->assertContains('ANCIEN', app(SelectionParrainage::class)->eligibles($suivante)->pluck('nom')->all());
        $this->get(route('parrainage.sessions.selection', $suivante))
            ->assertOk()->assertSee('Déjà parrainé : 2026-2027 · Admis en classe supérieure (14) · l’an prochain : passe en 4e · à reconduire');

        $this->get(route('oevs.show', $ligne->oev))->assertOk()->assertSee('Parrainages et suivi')->assertSee('recommandé pour un nouveau parrainage');
    }
}
