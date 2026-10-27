<?php

namespace Tests\Feature;

use App\Models\BeneficiaireSession;
use App\Models\Commune;
use App\Models\Ecole;
use App\Models\JournalActivite;
use App\Models\Oev;
use App\Models\PaiementEtablissement;
use App\Models\SessionParrainage;
use App\Models\User;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Onglet « Paiement » d'une session validée : établissements (banque, RIB, OEV, montant) et régions,
 * réception des fonds enregistrée par le niveau central, établissement qui suit la fiche jusqu'au paiement.
 */
class PaiementSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $central;
    private User $dr;
    private User $dp;
    private Commune $ici;
    private Commune $ailleurs;
    private SessionParrainage $session;
    private Ecole $ecoleIci;
    private Ecole $ecoleAilleurs;
    private Ecole $centre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);
        $this->ici = Commune::where('nom', 'Ouagadougou')->firstOrFail();
        $this->ailleurs = Commune::whereHas('province', fn ($q) => $q->where('region_id', '!=', $this->ici->province->region_id))->firstOrFail();
        $this->central = User::factory()->create()->assignRole('agent DGFE');
        $this->dr = User::factory()->create(['region_id' => $this->ici->province->region_id])->assignRole('DR');
        $this->dp = User::factory()->create(['region_id' => $this->ici->province->region_id, 'province_id' => $this->ici->province_id])->assignRole('DP');

        $this->ecoleIci = Ecole::create(['commune_id' => $this->ici->id, 'nom' => 'École Zinda', 'banque' => 'Coris Bank', 'rib' => 'BF042 01001 000123456789 45']);
        $this->ecoleAilleurs = Ecole::create(['commune_id' => $this->ailleurs->id, 'nom' => 'École lointaine']);
        $this->centre = Ecole::create(['nature' => Ecole::NATURE_CENTRE, 'commune_id' => $this->ici->id, 'nom' => 'Centre Wend Kuni', 'rib' => 'BF001 02002 000987654321 12']);
        $this->session = SessionParrainage::create([
            'description' => 'Session principale', 'annee' => '2026-2027', 'numero' => 1, 'type_appui' => SessionParrainage::TYPE_LES_DEUX,
            'enveloppe' => 1_000_000, 'date_ouverture' => '2026-10-01', 'etat' => SessionParrainage::ETAT_VALIDEE,
        ]);
    }

    private function retenu(Commune $commune, array $fiche, string $type, int $montant): BeneficiaireSession
    {
        $oev = Oev::create($fiche + [
            'nom' => 'ENFANT', 'prenom' => Str::random(6), 'sexe' => 'F', 'date_naissance' => '2015-01-01', 'statut' => 'vulnerable',
            'handicap' => false, 'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00', 'systeme_educatif' => 'classique',
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(), 'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
        ]);

        return $this->session->beneficiaires()->create([
            'oev_id' => $oev->id, 'type_appui' => $type, 'statut' => BeneficiaireSession::RETENU, 'score' => 10, 'region_id' => $oev->region_id,
            'etablissement_id' => $type === SessionParrainage::TYPE_FORMATION ? $oev->centre_formation_id : $oev->ecole_id,
            'frais_reels' => $montant, 'frais_estimes' => false, 'montant_retenu' => $montant,
        ]);
    }

    private function jeu(): array
    {
        return [
            $this->retenu($this->ici, ['ecole_id' => $this->ecoleIci->id], SessionParrainage::TYPE_SCOLAIRE, 40_000),
            $this->retenu($this->ici, ['ecole_id' => $this->ecoleIci->id], SessionParrainage::TYPE_SCOLAIRE, 35_000),
            $this->retenu($this->ici, ['centre_formation_id' => $this->centre->id, 'formation_professionnelle' => true], SessionParrainage::TYPE_FORMATION, 60_000),
            $this->retenu($this->ailleurs, ['ecole_id' => $this->ecoleAilleurs->id], SessionParrainage::TYPE_SCOLAIRE, 25_000),
            $this->retenu($this->ailleurs, [], SessionParrainage::TYPE_SCOLAIRE, 10_000),
        ];
    }

    public function test_les_etablissements_et_les_regions_se_consultent_sans_extraction(): void
    {
        $this->jeu();

        $this->actingAs($this->central)->get(route('parrainage.sessions.paiements', $this->session))
            ->assertOk()
            ->assertSee('École Zinda')->assertSee('Coris Bank')->assertSee('BF042 01001 000123456789 45')
            ->assertSee('Centre Wend Kuni')
            // École sans RIB, OEV sans établissement
            ->assertSee('École lointaine')->assertSee('RIB manquant')->assertSee('Établissement non renseigné')
            ->assertSee('1 OEV n’ont pas d’établissement', false)
            ->assertSee("75\u{00A0}000\u{00A0}FCFA", false)
            ->assertViewHas('totaux', fn ($t) => (int) $t->montant === 170_000 && (int) $t->nombre_etablissements === 3 && (int) $t->rib_manquants === 1);

        // Par région : nombre d'établissements et d'OEV, montant
        $this->get(route('parrainage.sessions.paiements', ['session' => $this->session, 'par' => 'region']))
            ->assertOk()
            ->assertViewHas('parRegion', function ($regions) {
                $ici = $regions->firstWhere('region_id', $this->ici->province->region_id);

                return (int) $ici->nombre_etablissements === 2 && (int) $ici->nombre_oev === 3 && (int) $ici->montant === 135_000;
            });

        // Une session encore en cours n'a pas de paiement
        $this->session->update(['etat' => SessionParrainage::ETAT_EN_COURS]);
        $this->get(route('parrainage.sessions.paiements', $this->session))->assertRedirect(route('parrainage.sessions.show', $this->session));
    }

    public function test_le_central_enregistre_la_reception_des_fonds_par_etablissement_ou_par_region(): void
    {
        $this->jeu();

        $this->actingAs($this->central)->post(route('parrainage.sessions.paiements.store', $this->session), [
            'etablissements' => [$this->ecoleIci->id], 'recu_le' => now()->toDateString(), 'reference' => 'VIR-2026-001',
        ])->assertSessionHas('success');
        $paiement = PaiementEtablissement::sole();
        $this->assertSame([75_000, 2, 'VIR-2026-001'], [$paiement->montant, $paiement->nombre_oev, $paiement->reference]);
        // Première réception : la session passe en « Paiement en cours », action tracée
        $this->assertSame(SessionParrainage::ETAT_PAIEMENT, $this->session->refresh()->etat);
        $this->assertTrue(JournalActivite::de($this->session)->where('action', 'session_parrainage.paiement')->exists());

        // Déjà payé : ignoré ; date dans le futur refusée
        $this->post(route('parrainage.sessions.paiements.store', $this->session), ['etablissements' => [$this->ecoleIci->id], 'recu_le' => now()->toDateString()])
            ->assertSessionHas('error');
        $this->post(route('parrainage.sessions.paiements.store', $this->session), ['etablissements' => [$this->centre->id], 'recu_le' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('recu_le');

        // Toute une région : ses établissements pas encore payés (ici le centre)
        $this->post(route('parrainage.sessions.paiements.store', $this->session), ['region_id' => $this->ici->province->region_id, 'recu_le' => now()->toDateString()])
            ->assertSessionHas('success');
        $this->assertEqualsCanonicalizing([$this->ecoleIci->id, $this->centre->id], PaiementEtablissement::pluck('etablissement_id')->all());

        // Annulation d'une saisie erronée
        $this->delete(route('parrainage.sessions.paiements.destroy', [$this->session, $paiement]))->assertSessionHas('success');
        $this->assertSame([$this->centre->id], PaiementEtablissement::pluck('etablissement_id')->all());
    }

    public function test_le_dr_consulte_sa_region_sans_enregistrer_et_le_dp_n_a_pas_acces(): void
    {
        $this->jeu();

        $this->actingAs($this->dr)->get(route('parrainage.sessions.paiements', $this->session))
            ->assertOk()->assertSee('École Zinda')->assertDontSee('École lointaine')->assertDontSee('Marquer comme reçus');
        $this->post(route('parrainage.sessions.paiements.store', $this->session), ['etablissements' => [$this->ecoleIci->id], 'recu_le' => now()->toDateString()])
            ->assertRedirect();
        $this->assertSame(0, PaiementEtablissement::count());

        $this->actingAs($this->dp)->get(route('parrainage.sessions.paiements', $this->session))->assertRedirect();
    }

    public function test_l_etablissement_suit_la_fiche_jusqu_au_paiement(): void
    {
        [$a, $b] = $this->jeu();
        $nouvelleEcole = Ecole::create(['commune_id' => $this->ici->id, 'nom' => 'Nouvelle école', 'rib' => 'BF999']);

        // Changement d'école avant le paiement (ex. au suivi annuel) : la session paiera la nouvelle école
        $a->oev->update(['ecole_id' => $nouvelleEcole->id]);
        $this->assertSame($nouvelleEcole->id, $a->refresh()->etablissement_id);

        // L'ancienne école de B a reçu les fonds : B y reste rattaché pour cette session
        PaiementEtablissement::create(['session_parrainage_id' => $this->session->id, 'etablissement_id' => $this->ecoleIci->id,
            'montant' => 35_000, 'nombre_oev' => 1, 'recu_le' => now()->toDateString()]);
        $b->oev->update(['ecole_id' => $nouvelleEcole->id]);
        $this->assertSame($this->ecoleIci->id, $b->refresh()->etablissement_id);

        // Session clôturée : plus aucun changement
        $this->session->update(['etat' => SessionParrainage::ETAT_CLOTUREE]);
        $a->oev->update(['ecole_id' => $this->ecoleIci->id]);
        $this->assertSame($nouvelleEcole->id, $a->refresh()->etablissement_id);
    }

    private function classeur(\Illuminate\Testing\TestResponse $reponse): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($chemin, $reponse->streamedContent());
        $classeur = \PhpOffice\PhpSpreadsheet\IOFactory::load($chemin);
        @unlink($chemin);

        return $classeur;
    }

    /** Lignes non vides d'une feuille (valeurs calculées). */
    private function lignes(\PhpOffice\PhpSpreadsheet\Spreadsheet $classeur, string $feuille): array
    {
        return array_values(array_filter($classeur->getSheetByName($feuille)->toArray(null, true, false), fn ($l) => array_filter($l, fn ($v) => $v !== null && $v !== '')));
    }

    public function test_le_fichier_de_paiement_reprend_les_feuilles_et_colonnes_choisies(): void
    {
        $this->jeu();
        PaiementEtablissement::create(['session_parrainage_id' => $this->session->id, 'etablissement_id' => $this->centre->id,
            'montant' => 60_000, 'nombre_oev' => 1, 'recu_le' => '2026-11-15', 'reference' => 'VIR-9']);
        $ici = $this->ici->province->region->nom;
        $ailleurs = $this->ailleurs->province->region->nom;

        $reponse = $this->actingAs($this->central)->get(route('parrainage.sessions.paiements.export', ['session' => $this->session,
            'feuilles' => ['etablissements', 'regions', 'nominatif'],
            'colonnes' => ['etablissements' => ['region', 'banque', 'rib', 'paiement'], 'regions' => ['nombre_oev', 'montant_paye', 'reste'],
                'nominatif' => ['etablissement', 'prenom']]]));
        $reponse->assertOk();
        $classeur = $this->classeur($reponse);

        // Feuilles : par établissement, par région, puis une feuille nominative par région
        $this->assertEqualsCanonicalizing(['Par établissement', 'Par région', $ailleurs, $ici], $classeur->getSheetNames());

        // Par établissement : colonnes choisies (+ obligatoires) dans l'ordre du catalogue, sous-totaux, statut de paiement
        $etab = $this->lignes($classeur, 'Par établissement');
        $entete = array_search(['Région', 'Établissement', 'Banque', 'RIB', 'Nombre d’OEV', 'Montant à verser (FCFA)', 'Paiement'], $etab, true);
        $this->assertNotFalse($entete, 'En-tête des colonnes choisies');
        $texte = collect($etab)->flatten()->filter()->implode(' | ');
        $this->assertStringContainsString('BF042 01001 000123456789 45', $texte);
        $this->assertStringContainsString('RIB manquant', $texte);
        $this->assertStringContainsString('Reçu le 15/11/2026', $texte);
        $this->assertStringContainsString('Total région ' . $ici, $texte);
        $this->assertSame(['Total général', null, null, null, 5, 170000, null], end($etab));

        // Par région : nombre d'OEV, reçu, reste
        $regions = $this->lignes($classeur, 'Par région');
        $this->assertContains([$ici, 3, 135000, 60000, 75000], $regions);
        $this->assertSame(['Total', 5, 170000, 60000, 110000], end($regions));

        // Nominatif de la région : rangé par établissement, sous-total par établissement
        $nominatif = collect($this->lignes($classeur, $ici))->flatten()->filter()->implode(' | ');
        $this->assertStringContainsString('Total École Zinda (2 OEV)', $nominatif);
        $this->assertStringContainsString('Total région (3 OEV)', $nominatif);
        $this->assertTrue(JournalActivite::where('action', 'extraction.paiement')->exists());

        // Établissements à payer seulement, une région : le centre payé disparaît
        $reponse = $this->get(route('parrainage.sessions.paiements.export', ['session' => $this->session, 'feuilles' => ['etablissements'],
            'paiement' => 'a_payer', 'region_id' => $this->ici->province->region_id]));
        $texte = collect($this->lignes($this->classeur($reponse), 'Par établissement'))->flatten()->filter()->implode(' | ');
        $this->assertStringContainsString('École Zinda', $texte);
        $this->assertStringNotContainsString('Centre Wend Kuni', $texte);
        $this->assertStringNotContainsString('École lointaine', $texte);

        // Aucune feuille : refusé ; le DR n'obtient que sa région
        $this->get(route('parrainage.sessions.paiements.export', ['session' => $this->session]))->assertSessionHasErrors('feuilles');
        $reponse = $this->actingAs($this->dr)->get(route('parrainage.sessions.paiements.export', ['session' => $this->session, 'feuilles' => ['nominatif']]));
        $this->assertSame([$ici], $this->classeur($reponse)->getSheetNames());
    }
}
