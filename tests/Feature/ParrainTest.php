<?php

namespace Tests\Feature;

use App\Models\AppuiPartenaire;
use App\Models\Commune;
use App\Models\JournalActivite;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\User;
use App\Services\Parrainage\ParrainageService;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ParrainTest extends TestCase
{
    use RefreshDatabase;

    private User $central;
    private User $dp;
    private User $dr;

    private Commune $ouagadougou;
    private Commune $ailleurs;

    private Parrain $parrain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);

        $this->ouagadougou = Commune::where('nom', 'Ouagadougou')->firstOrFail();
        // Une commune d'une autre région, hors de la zone du DP et du DR
        $this->ailleurs = Commune::whereHas('province', fn ($q) => $q->where('region_id', '!=', $this->ouagadougou->province->region_id))->firstOrFail();

        $province = $this->ouagadougou->province;
        $this->central = User::factory()->create()->assignRole('agent DGFE');
        $this->dp = User::factory()->create(['region_id' => $province->region_id, 'province_id' => $province->id])->assignRole('DP');
        $this->dr = User::factory()->create(['region_id' => $province->region_id])->assignRole('DR');
        $this->parrain = Parrain::create(['type' => 'ong', 'nom' => 'ONG Espoir', 'actif' => true]);
    }

    private function oev(Commune $commune, string $etat = Oev::ETAT_INTEGRE): Oev
    {
        $integre = $etat === Oev::ETAT_INTEGRE;

        return Oev::create([
            'nom' => 'ENFANT', 'prenom' => Str::random(6), 'sexe' => 'F', 'date_naissance' => '2015-03-01',
            'statut' => 'orphelin_pere', 'handicap' => false, 'systeme_educatif' => 'classique',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_actuel' => 'École A', 'type_etablissement' => 'public', 'classe' => 'CM1', 'frais_scolarite' => 0,
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'numero_dossier' => Oev::genererNumeroDossier(),
            'statut_dossier' => $etat,
            'code' => $integre ? Oev::genererCode() : null,
            'integre_at' => $integre ? now() : null,
        ]);
    }

    private function nature(string $code): NatureAppui
    {
        return NatureAppui::where('code', $code)->firstOrFail();
    }

    private function donneesAppui(Oev $oev, array $surcharge = []): array
    {
        return $surcharge + [
            'oev_id' => $oev->id,
            'parrain_id' => $this->parrain->id,
            'annee' => '2026-2027',
            'nature_appui_id' => $this->nature('scolaire')->id,
            'montant' => '45 000',
            'date_debut' => '2026-10-01',
            'date_fin' => '2027-06-30',
        ];
    }

    private function appui(Oev $oev, string $nature, string $annee = '2026-2027'): AppuiPartenaire
    {
        return AppuiPartenaire::create([
            'oev_id' => $oev->id, 'parrain_id' => $this->parrain->id, 'annee' => $annee, 'nature_appui_id' => $this->nature($nature)->id,
        ]);
    }

    public function test_le_niveau_central_enregistre_un_parrain_avec_sa_zone(): void
    {
        $region = $this->ouagadougou->province->region;
        $province = $this->ailleurs->province;

        $this->actingAs($this->central)->post(route('parrainage.parrains.store'), [
            'type' => 'entreprise', 'nom' => 'Société Solidaire', 'contact_nom' => 'M. Kaboré', 'telephone' => '+226 70 11 22 33',
            'email' => 'contact@solidaire.bf', 'actif' => '1',
            'zones' => [
                ['region_id' => $region->id, 'province_id' => ''],                      // toute la région
                ['region_id' => $province->region_id, 'province_id' => $province->id],  // une province d'une autre région
                ['region_id' => $region->id, 'province_id' => $this->ouagadougou->province_id], // redondante : région déjà entière
                ['region_id' => '', 'province_id' => ''],                               // ligne laissée vide
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $parrain = Parrain::where('nom', 'Société Solidaire')->sole();
        $this->assertCount(2, $parrain->zones);
        $this->assertSame([$province->id], $parrain->zones->pluck('province_id')->filter()->values()->all());
        $this->assertEqualsCanonicalizing([$region->id, $province->region_id], $parrain->zones->pluck('region_id')->all());
        $this->assertSame(1, Parrain::intervenantDans($province->region)->where('id', $parrain->id)->count());
        $this->assertTrue(JournalActivite::de($parrain)->where('action', 'parrain.creation')->exists());

        $this->actingAs($this->central)->get(route('parrainage.parrains.show', $parrain))->assertOk()->assertSee('M. Kaboré');
        $this->actingAs($this->central)->get(route('parrainage.parrains.index', ['region_id' => $region->id]))->assertOk()->assertSee('Société Solidaire');
        $this->actingAs($this->central)->get(route('parrainage.parrains.edit', $parrain))->assertOk()->assertSee('Toute la région');
    }

    public function test_une_province_doit_appartenir_a_la_region_choisie(): void
    {
        $this->actingAs($this->central)->post(route('parrainage.parrains.store'), [
            'type' => 'ong', 'nom' => 'ONG Mauvaise zone', 'actif' => '1',
            'zones' => [['region_id' => $this->ouagadougou->province->region_id, 'province_id' => $this->ailleurs->province_id]],
        ])->assertSessionHasErrors('zones.0.province_id');

        $this->assertDatabaseMissing('parrains', ['nom' => 'ONG Mauvaise zone']);
    }

    public function test_seul_le_niveau_central_gere_le_repertoire(): void
    {
        $this->actingAs($this->dp)->get(route('parrainage.parrains.index'))->assertOk()->assertSee('ONG Espoir')->assertDontSee('Nouveau parrain');
        $this->actingAs($this->dp)->post(route('parrainage.parrains.store'), ['type' => 'ong', 'nom' => 'Interdit'])->assertRedirect();
        $this->actingAs($this->dr)->put(route('parrainage.parrains.update', $this->parrain), ['type' => 'ong', 'nom' => 'Renommé'])->assertRedirect();

        $this->assertDatabaseMissing('parrains', ['nom' => 'Interdit']);
        $this->assertSame('ONG Espoir', $this->parrain->refresh()->nom);
    }

    public function test_un_parrain_avec_des_appuis_se_desactive_sans_se_supprimer(): void
    {
        $this->appui($this->oev($this->ouagadougou), 'sante');

        $this->actingAs($this->central)->delete(route('parrainage.parrains.destroy', $this->parrain))->assertSessionHas('error');
        $this->assertModelExists($this->parrain);

        $this->actingAs($this->central)->put(route('parrainage.parrains.update', $this->parrain), ['type' => 'ong', 'nom' => 'ONG Espoir', 'actif' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($this->parrain->refresh()->actif);
        $this->assertTrue(JournalActivite::de($this->parrain)->where('description', 'like', 'Désactivation%')->exists());

        // Un parrain désactivé ne reçoit plus de nouvel appui
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($this->oev($this->ouagadougou)))
            ->assertSessionHasErrors('parrain_id');

        // Sans appui ni contribution, la suppression est possible
        $vierge = Parrain::create(['type' => 'particulier', 'nom' => 'Particulier']);
        $this->actingAs($this->central)->delete(route('parrainage.parrains.destroy', $vierge))->assertSessionHasNoErrors();
        $this->assertModelMissing($vierge);
    }

    public function test_un_engagement_s_enregistre_avec_sa_convention(): void
    {
        Storage::fake('local');

        $this->actingAs($this->central)->post(route('parrainage.parrains.engagements.store', $this->parrain), [
            'date_engagement' => '2026-09-15', 'duree_mois' => 24, 'nombre_oev_prevu' => 50, 'montant_prevu' => '5 000 000',
            'natures_appui' => [$this->nature('scolaire')->id, $this->nature('sante')->id],
            'convention' => UploadedFile::fake()->create('convention.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $engagement = $this->parrain->engagements()->sole();
        $this->assertSame(5_000_000, $engagement->montant_prevu);
        $this->assertSame(['Scolaire', 'Santé'], $engagement->natures()->pluck('libelle')->all());
        Storage::disk('local')->assertExists($engagement->convention_chemin);

        $this->actingAs($this->dp)->get(route('parrainage.parrains.engagements.convention', [$this->parrain, $engagement]))->assertOk();
    }

    public function test_le_dp_enregistre_un_appui_pour_un_oev_integre_de_sa_province(): void
    {
        $oev = $this->oev($this->ouagadougou);

        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($oev))
            ->assertRedirect(route('parrainage.appuis.index'))
            ->assertSessionHasNoErrors();

        $appui = AppuiPartenaire::sole();
        $this->assertSame(45_000, $appui->montant);
        $this->assertSame($this->dp->id, $appui->created_by);
        $this->assertTrue(JournalActivite::de($appui)->where('action', 'appui_partenaire.creation')->exists());

        // Hors de sa province, ou dossier non intégré : refusé
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($this->oev($this->ailleurs)))
            ->assertSessionHasErrors('oev_id');
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($this->oev($this->ouagadougou, Oev::ETAT_VALIDE)))
            ->assertSessionHasErrors('oev_id');
        $this->assertSame(1, AppuiPartenaire::count());
    }

    public function test_rg01_un_deuxieme_appui_de_meme_nature_exige_une_confirmation_avec_motif(): void
    {
        $oev = $this->oev($this->ouagadougou);
        $this->appui($oev, 'scolaire');

        // Même nature, même année : avertissement, rien n'est enregistré
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($oev))
            ->assertRedirect()
            ->assertSessionHas('doublons');
        $this->assertSame(1, AppuiPartenaire::count());

        // Confirmation sans motif : refusée
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($oev, ['confirmer_doublon' => '1']))
            ->assertSessionHasErrors('motif_doublon');
        $this->assertSame(1, AppuiPartenaire::count());

        // Confirmation avec motif : enregistré et tracé
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($oev, ['confirmer_doublon' => '1', 'motif_doublon' => 'Complément des frais d’internat']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Complément des frais d’internat', AppuiPartenaire::latest('id')->first()->motif_doublon);

        // Autre nature, ou autre année : aucun avertissement
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($oev, ['nature_appui_id' => $this->nature('sante')->id]))
            ->assertSessionMissing('doublons')->assertSessionHasNoErrors();
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($oev, ['annee' => '2027-2028']))
            ->assertSessionMissing('doublons')->assertSessionHasNoErrors();
        $this->assertSame(4, AppuiPartenaire::count());
    }

    public function test_la_nature_autre_exige_une_precision(): void
    {
        $oev = $this->oev($this->ouagadougou);

        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($oev, ['nature_appui_id' => $this->nature('autre')->id]))
            ->assertSessionHasErrors('nature_precision');
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $this->donneesAppui($oev, ['nature_appui_id' => $this->nature('autre')->id, 'nature_precision' => 'Kit d’hygiène']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Autre (Kit d’hygiène)', AppuiPartenaire::sole()->libelleNature());
    }

    public function test_est_deja_appuye_ne_compte_que_la_meme_nature_la_meme_annee(): void
    {
        $service = $this->app->make(ParrainageService::class);
        $oev = $this->oev($this->ouagadougou);
        $autre = $this->oev($this->ailleurs);
        $this->appui($oev, 'sante');
        $this->appui($autre, 'scolaire');

        $this->assertTrue($service->estDejaAppuye($oev->id, '2026-2027', 'sante'));
        $this->assertTrue($service->estDejaAppuye($oev->id, '2026-2027', $this->nature('sante')->id));
        $this->assertFalse($service->estDejaAppuye($oev->id, '2026-2027', 'scolaire'));
        $this->assertFalse($service->estDejaAppuye($oev->id, '2027-2028', 'sante'));
        $this->assertFalse($service->estDejaAppuye($oev->id, '2026-2027', 'nature_inconnue'));

        $this->assertSame([$autre->id], $service->getOevAppuyesParPartenaire('2026-2027', 'scolaire'));
        $this->assertSame([$oev->id], $service->getOevAppuyesParPartenaire('2026-2027', 'sante'));
        $this->assertSame([], $service->getOevAppuyesParPartenaire('2027-2028', 'scolaire'));
    }

    public function test_chacun_ne_voit_que_les_appuis_de_sa_zone(): void
    {
        $proche = $this->oev($this->ouagadougou);
        $lointain = $this->oev($this->ailleurs);
        $this->appui($proche, 'sante');
        $appuiLointain = $this->appui($lointain, 'sante');

        foreach ([$this->dp, $this->dr] as $agent) {
            $this->actingAs($agent)->get(route('parrainage.appuis.index'))
                ->assertOk()->assertSee($proche->prenom)->assertDontSee($lointain->prenom);
        }
        $this->actingAs($this->central)->get(route('parrainage.appuis.index'))
            ->assertOk()->assertSee($proche->prenom)->assertSee($lointain->prenom);

        // Le DP ne modifie pas un appui hors de sa zone ; le DR n'enregistre pas d'appui
        $this->actingAs($this->dp)->get(route('parrainage.appuis.edit', $appuiLointain))->assertRedirect();
        $this->actingAs($this->dr)->post(route('parrainage.appuis.store'), $this->donneesAppui($proche, ['nature_appui_id' => $this->nature('alimentaire')->id]))->assertRedirect();
        $this->assertSame(2, AppuiPartenaire::count());
    }

    public function test_la_recherche_d_oev_se_limite_aux_oev_integres_de_la_zone(): void
    {
        $proche = $this->oev($this->ouagadougou);
        $this->oev($this->ailleurs);
        $this->oev($this->ouagadougou, Oev::ETAT_SOUMIS);

        $this->actingAs($this->dp)->getJson(route('parrainage.appuis.recherche-oev', ['q' => 'enfant']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $proche->id);
    }

    public function test_l_import_excel_enregistre_les_lignes_valides_et_liste_les_rejets(): void
    {
        $oev = $this->oev($this->ouagadougou);
        $dejaAppuye = $this->oev($this->ouagadougou);
        $this->appui($dejaAppuye, 'alimentaire');

        $classeur = new Spreadsheet();
        $classeur->getActiveSheet()->fromArray([
            ['code_oev', 'parrain_id', 'annee', 'nature', 'nature_precision', 'montant', 'date_debut', 'date_fin', 'observations', 'motif_doublon'],
            ['Code OEV', 'N° du parrain', 'Année', 'Nature', 'Précision', 'Montant', 'Début', 'Fin', 'Observations', 'Motif'],
            [$oev->code, $this->parrain->id, '2026-2027', 'scolaire', null, 45000, '01/10/2026', '30/06/2027', 'Frais de scolarité', null],
            ['OEV-9999-9999', $this->parrain->id, '2026-2027', 'scolaire', null, 10000, null, null, null, null],
            [$dejaAppuye->code, $this->parrain->id, '2026-2027', 'alimentaire', null, null, null, null, null, null],
            [$dejaAppuye->code, $this->parrain->id, '2026-2027', 'alimentaire', null, null, null, null, null, 'Période de soudure'],
        ]);
        $chemin = tempnam(sys_get_temp_dir(), 'import') . '.xlsx';
        (new Xlsx($classeur))->save($chemin);

        $this->actingAs($this->dp)->post(route('parrainage.appuis.import.store'), [
            'fichier' => new UploadedFile($chemin, 'appuis.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect(route('parrainage.appuis.import'));

        $rapport = session('rapportImport');
        $this->assertSame(2, $rapport['importes']);
        $this->assertSame([4, 5], array_column($rapport['rejets'], 'ligne'));
        $this->assertStringContainsString('OEV-9999-9999', $rapport['rejets'][0]['motifs'][0]);
        $this->assertStringContainsString('motif_doublon', $rapport['rejets'][1]['motifs'][0]);

        $importe = AppuiPartenaire::where('oev_id', $oev->id)->sole();
        $this->assertSame(45_000, $importe->montant);
        $this->assertSame('2026-10-01', $importe->date_debut->toDateString());
        $this->assertSame('Période de soudure', AppuiPartenaire::where('oev_id', $dejaAppuye->id)->latest('id')->first()->motif_doublon);
        $this->assertTrue(JournalActivite::where('action', 'appui_partenaire.import')->exists());

        $this->actingAs($this->dp)->get(route('parrainage.appuis.import'))->assertOk()->assertSee('OEV-9999-9999');
        @unlink($chemin);
    }

    public function test_les_ecrans_des_appuis_et_le_modele_excel(): void
    {
        $oev = $this->oev($this->ouagadougou);
        $appui = $this->appui($oev, 'scolaire');

        $this->actingAs($this->dp)->get(route('parrainage.appuis.create'))->assertOk()->assertSee('ONG Espoir');
        $this->actingAs($this->dp)->get(route('parrainage.appuis.edit', $appui))->assertOk()->assertSee($oev->code);
        $this->actingAs($this->dp)->get(route('parrainage.parrains.show', $this->parrain))->assertOk()->assertSee('2026-2027');

        $reponse = $this->actingAs($this->dp)->get(route('parrainage.appuis.modele'));
        $reponse->assertOk()->assertDownload('modele-import-appuis.xlsx');
    }
}
