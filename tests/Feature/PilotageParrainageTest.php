<?php

namespace Tests\Feature;

use App\Models\AppuiPartenaire;
use App\Models\Commune;
use App\Models\JournalActivite;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Services\Parrainage\SourceSelection;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PilotageParrainageTest extends TestCase
{
    use RefreshDatabase;

    private User $central;
    private User $dr;
    private User $dp;
    private Commune $ouagadougou;
    private Commune $ailleurs;
    private SessionParrainage $session;
    private Parrain $parrain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);

        $this->ouagadougou = Commune::where('nom', 'Ouagadougou')->firstOrFail();
        $this->ailleurs = Commune::whereHas('province', fn ($q) => $q->where('region_id', '!=', $this->ouagadougou->province->region_id))->firstOrFail();
        $province = $this->ouagadougou->province;
        $this->central = User::factory()->create()->assignRole('agent DGFE');
        $this->dr = User::factory()->create(['region_id' => $province->region_id])->assignRole('DR');
        $this->dp = User::factory()->create(['region_id' => $province->region_id, 'province_id' => $province->id])->assignRole('DP');

        $this->session = SessionParrainage::create([
            'description' => 'Session principale', 'annee' => '2026-2027', 'numero' => 1, 'type_appui' => SessionParrainage::TYPE_SCOLAIRE,
            'enveloppe' => 1_000_000, 'plafond_beneficiaire' => 50_000, 'source_financement' => SessionParrainage::SOURCE_ETAT,
            'date_ouverture' => '2026-10-01', 'etat' => SessionParrainage::ETAT_VALIDEE,
        ]);
        $this->parrain = Parrain::create(['type' => 'ong', 'nom' => 'ONG Espoir', 'actif' => true]);
    }

    private function oev(Commune $commune, array $attributs = []): Oev
    {
        return Oev::create($attributs + [
            'nom' => 'ENFANT', 'prenom' => Str::random(6), 'sexe' => 'F', 'date_naissance' => now()->subYears(10)->toDateString(),
            'statut' => 'orphelin_pere', 'handicap' => false, 'systeme_educatif' => 'classique', 'niveau_etude' => 'primaire',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_actuel' => 'École A', 'type_etablissement' => 'public', 'classe' => 'CM1', 'frais_scolarite' => 30_000,
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
            'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
        ]);
    }

    /** Simule les modules de Nakoulma : liste définitive, établissements (dont un sans RIB), liste d'attente, résultats. */
    private function brancherSelection(array $definitive, array $attente = [], array $resultats = []): void
    {
        $this->app->instance(SourceSelection::class, new class($definitive, $attente, $resultats) implements SourceSelection
        {
            public function __construct(private array $definitive, private array $attente, private array $resultats)
            {
            }

            public function disponible(): bool
            {
                return true;
            }

            public function eligiblesParRegion(SessionParrainage $session): array
            {
                return [];
            }

            public function montantEngage(SessionParrainage $session): ?int
            {
                return (int) collect($this->definitive)->sum('montant_retenu');
            }

            public function listeDefinitive(SessionParrainage $session): Collection
            {
                return collect($this->definitive);
            }

            public function listeAttente(SessionParrainage $session): Collection
            {
                return collect($this->attente);
            }

            public function etablissements(): Collection
            {
                return collect([
                    ['id' => 1, 'nom' => 'Lycée Zinda', 'type' => 'Public', 'region_id' => null, 'province_id' => null, 'commune_id' => null,
                        'banque' => 'BICIA-B', 'code_banque' => 'BF023', 'code_guichet' => '01001', 'numero_compte' => '123456789', 'cle_rib' => '45', 'titulaire' => 'Lycée Zinda'],
                    ['id' => 2, 'nom' => 'École Wend Panga', 'type' => 'Privé', 'region_id' => null, 'province_id' => null, 'commune_id' => null,
                        'banque' => null, 'code_banque' => null, 'code_guichet' => null, 'numero_compte' => null, 'cle_rib' => null, 'titulaire' => null],
                ])->keyBy('id');
            }

            public function naturesAppuiEtat(int $oevId, string $annee): array
            {
                return [];
            }

            public function resultatsScolaires(string $annee): Collection
            {
                return collect($this->resultats);
            }
        });
    }

    private function feuilles(TestResponse $reponse): Spreadsheet
    {
        $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($chemin, $reponse->streamedContent());
        $classeur = IOFactory::load($chemin);
        @unlink($chemin);

        return $classeur;
    }

    private function contenu(Spreadsheet $classeur, string $feuille): string
    {
        return collect($classeur->getSheetByName($feuille)->toArray())->flatten()->filter()->implode(' | ');
    }

    public function test_le_tableau_de_bord_sans_module_selection_compte_les_appuis_des_partenaires(): void
    {
        $appuye = $this->oev($this->ouagadougou, ['sexe' => 'M', 'vulnerabilites' => ['orphelin', 'precarite']]);
        $this->oev($this->ouagadougou);
        $this->oev($this->ailleurs);
        AppuiPartenaire::create(['oev_id' => $appuye->id, 'parrain_id' => $this->parrain->id, 'annee' => '2026-2027',
            'nature_appui_id' => NatureAppui::where('code', 'sante')->value('id'), 'montant' => 20_000]);

        $this->actingAs($this->central)->get(route('parrainage.pilotage.index', ['annee' => '2026-2027']))
            ->assertOk()
            ->assertSee('Données non disponibles')
            ->assertSee('ONG Espoir')
            ->assertSee('Précarité du ménage')
            ->assertViewHas('indicateurs', fn ($i) => $i['couverture']['eligibles'] === 3
                && $i['couverture']['partenaires'] === 1
                && $i['couverture']['etat'] === null
                && $i['budget']['enveloppe'] === 1_000_000
                && $i['budget']['engage'] === null
                && $i['profil']['total'] === 1);

        // Le DP ne compte que sa province
        $this->actingAs($this->dp)->get(route('parrainage.pilotage.index', ['annee' => '2026-2027']))
            ->assertOk()
            ->assertViewHas('indicateurs', fn ($i) => $i['couverture']['eligibles'] === 2);
    }

    public function test_le_tableau_de_bord_avec_les_donnees_de_l_etat(): void
    {
        $proche = $this->oev($this->ouagadougou);
        $lointain = $this->oev($this->ailleurs);
        $this->brancherSelection(
            [
                ['oev_id' => $proche->id, 'type_appui' => 'scolaire', 'etablissement_id' => 1, 'frais_reels' => 60_000, 'montant_retenu' => 50_000],
                ['oev_id' => $lointain->id, 'type_appui' => 'scolaire', 'etablissement_id' => 2, 'frais_reels' => 30_000, 'montant_retenu' => 30_000],
            ],
            [],
            [['oev_id' => $proche->id, 'annee' => '2026-2027', 'resultat' => 'admis'], ['oev_id' => $lointain->id, 'annee' => '2026-2027', 'resultat' => 'redouble']],
        );

        $this->actingAs($this->central)->get(route('parrainage.pilotage.index', ['annee' => '2026-2027', 'session_id' => $this->session->id]))
            ->assertOk()
            ->assertViewHas('indicateurs', fn ($i) => $i['budget']['engage'] === 80_000
                && $i['budget']['taux'] === 8.0
                && $i['couverture']['etat'] === 2
                && $i['couverture']['taux'] === 100.0
                && $i['resultats'][0]['taux'] === 50.0);

        $this->actingAs($this->dr)->get(route('parrainage.pilotage.index', ['annee' => '2026-2027']))
            ->assertOk()
            ->assertViewHas('indicateurs', fn ($i) => $i['budget']['engage'] === 50_000 && $i['couverture']['etat'] === 1);
    }

    public function test_la_liste_pour_paiement_signale_le_rib_manquant_et_totalise(): void
    {
        $a = $this->oev($this->ouagadougou, ['nom' => 'KABORE']);
        $b = $this->oev($this->ouagadougou, ['nom' => 'ZONGO']);
        $c = $this->oev($this->ailleurs, ['nom' => 'SAWADOGO']);
        $this->brancherSelection([
            ['oev_id' => $a->id, 'type_appui' => 'scolaire', 'etablissement_id' => 1, 'frais_reels' => 60_000, 'montant_retenu' => 50_000],
            ['oev_id' => $b->id, 'type_appui' => 'scolaire', 'etablissement_id' => 1, 'frais_reels' => 40_000, 'montant_retenu' => 40_000],
            ['oev_id' => $c->id, 'type_appui' => 'scolaire', 'etablissement_id' => 2, 'frais_reels' => 30_000, 'montant_retenu' => 30_000],
        ]);

        $reponse = $this->actingAs($this->central)->get(route('parrainage.pilotage.paiement', ['session_id' => $this->session->id, 'format' => 'xlsx']));
        $reponse->assertOk();
        $classeur = $this->feuilles($reponse);

        $this->assertSame(['Par établissement', 'Nominatif'], $classeur->getSheetNames());
        $parEtablissement = $this->contenu($classeur, 'Par établissement');
        $this->assertStringContainsString('Session 2026-2027 · n°1', $parEtablissement);
        $this->assertStringContainsString('RIB manquant', $parEtablissement);
        $this->assertStringContainsString('Total général', $parEtablissement);
        $this->assertStringContainsString('120000', str_replace([',', ' '], '', $parEtablissement));
        $this->assertStringContainsString('SAWADOGO', $this->contenu($classeur, 'Nominatif'));

        $journal = JournalActivite::where('action', 'extraction.paiement')->sole();
        $this->assertSame(3, $journal->details['lignes']);
        $this->assertSame($this->central->id, $journal->user_id);

        // Le DR ne reçoit que sa région ; PDF disponible aussi
        $reponseDr = $this->actingAs($this->dr)->get(route('parrainage.pilotage.paiement', ['session_id' => $this->session->id, 'format' => 'xlsx']));
        $this->assertStringNotContainsString('SAWADOGO', $this->contenu($this->feuilles($reponseDr), 'Nominatif'));
        $this->actingAs($this->dr)->get(route('parrainage.pilotage.paiement', ['session_id' => $this->session->id, 'format' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_la_liste_pour_paiement_est_reservee_au_central_et_aux_dr_pour_une_session_validee(): void
    {
        $this->actingAs($this->dp)->get(route('parrainage.pilotage.paiement', ['session_id' => $this->session->id, 'format' => 'xlsx']))->assertRedirect();

        $this->session->update(['etat' => SessionParrainage::ETAT_EN_COURS]);
        $this->actingAs($this->central)->get(route('parrainage.pilotage.paiement', ['session_id' => $this->session->id, 'format' => 'xlsx']))
            ->assertSessionHasErrors('session_id');

        $this->assertSame(0, JournalActivite::where('action', 'extraction.paiement')->count());
    }

    public function test_la_liste_pour_un_parrain_est_anonymisee_et_exclut_les_oev_deja_appuyes(): void
    {
        $scolaire = NatureAppui::where('code', 'scolaire')->firstOrFail();
        $this->parrain->zones()->create(['region_id' => $this->ouagadougou->province->region_id]);
        $this->parrain->engagements()->create(['date_engagement' => '2026-09-01', 'natures_appui' => [$scolaire->id]]);

        $propose = $this->oev($this->ouagadougou, ['nom' => 'KABORE', 'prenom' => 'Alice', 'niveau_priorite' => 'eleve']);
        $dejaAppuye = $this->oev($this->ouagadougou, ['nom' => 'OUEDRAOGO']);
        $this->oev($this->ailleurs, ['nom' => 'HORSZONE']);
        $this->oev($this->ouagadougou, ['nom' => 'DESACTIVE', 'desactivation_etat' => Oev::DESACTIVE]);
        AppuiPartenaire::create(['oev_id' => $dejaAppuye->id, 'parrain_id' => $this->parrain->id, 'annee' => '2026-2027', 'nature_appui_id' => $scolaire->id]);

        // Aperçu : préfiltré sur la zone et la nature du parrain
        $this->actingAs($this->dp)->get(route('parrainage.pilotage.extractions', ['parrain_id' => $this->parrain->id, 'annee' => '2026-2027']))
            ->assertOk()
            ->assertViewHas('liste', fn ($liste) => $liste->pluck('id')->all() === [$propose->id]);

        $reponse = $this->actingAs($this->dp)->get(route('parrainage.pilotage.liste-parrain', [
            'parrain_id' => $this->parrain->id, 'format' => 'xlsx', 'filtre' => 1, 'source' => 'eligibles',
            'annee' => '2026-2027', 'nature_id' => $scolaire->id, 'zone_parrain' => 1,
        ]));
        $contenu = $this->contenu($this->feuilles($reponse), 'OEV proposés');
        $this->assertStringContainsString('Initiales', $contenu);
        $this->assertStringContainsString('KA', $contenu);
        $this->assertStringNotContainsString('KABORE', $contenu);
        $this->assertStringNotContainsString('Alice', $contenu);
        $this->assertSame(1, JournalActivite::where('action', 'extraction.liste_parrain')->sole()->details['lignes']);
    }

    public function test_l_identite_complete_est_reservee_au_niveau_central_avec_confirmation(): void
    {
        $this->oev($this->ouagadougou, ['nom' => 'KABORE', 'prenom' => 'Alice']);
        $parametres = ['parrain_id' => $this->parrain->id, 'format' => 'xlsx', 'filtre' => 1, 'source' => 'eligibles', 'annee' => '2026-2027', 'identite_complete' => 1];

        $this->actingAs($this->dp)->get(route('parrainage.pilotage.liste-parrain', $parametres + ['confirmation_identite' => 1]))
            ->assertSessionHasErrors('identite_complete');
        $this->actingAs($this->central)->get(route('parrainage.pilotage.liste-parrain', $parametres))
            ->assertSessionHasErrors('confirmation_identite');
        $this->assertSame(0, JournalActivite::count());

        $reponse = $this->actingAs($this->central)->get(route('parrainage.pilotage.liste-parrain', $parametres + ['confirmation_identite' => 1]));
        $contenu = $this->contenu($this->feuilles($reponse), 'OEV proposés');
        $this->assertStringContainsString('KABORE', $contenu);
        $this->assertStringContainsString('Alice', $contenu);

        $journal = JournalActivite::where('action', 'extraction.liste_parrain_identite')->sole();
        $this->assertTrue($journal->details['identite_complete']);
        $this->assertSame($this->central->id, $journal->user_id);
    }

    public function test_les_ecrans_d_extraction_s_affichent_selon_les_droits(): void
    {
        $this->actingAs($this->central)->get(route('parrainage.pilotage.extractions', ['vue' => 'paiement']))
            ->assertOk()->assertSee('Liste pour paiement')->assertSee('Session principale');
        // Le DP n'a pas accès à la liste pour paiement : la vue parrain s'affiche
        $this->actingAs($this->dp)->get(route('parrainage.pilotage.extractions', ['vue' => 'paiement']))
            ->assertOk()->assertViewHas('vue', 'parrain')->assertDontSee('Choisir une session validée');
        $this->actingAs($this->dp)->get(route('parrainage.pilotage.liste-parrain', ['parrain_id' => $this->parrain->id, 'format' => 'pdf', 'filtre' => 1]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
