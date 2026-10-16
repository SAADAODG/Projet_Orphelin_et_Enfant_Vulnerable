<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Ecole;
use App\Models\Oev;
use App\Models\User;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\EcoleSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Paramètres > Écoles et choix de l'école dans le formulaire OEV. */
class EcoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dp;

    private Commune $ouagadougou;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);
        $this->ouagadougou = Commune::where('nom', 'Ouagadougou')->firstOrFail();

        $this->admin = User::factory()->create()->assignRole('administrateur');
        $province = $this->ouagadougou->province;
        $this->dp = User::factory()->create(['region_id' => $province->region_id, 'province_id' => $province->id])->assignRole('DP');
    }

    private function ecole(array $attributs = []): Ecole
    {
        return Ecole::create($attributs + ['commune_id' => $this->ouagadougou->id, 'ceb' => 'OUAGA N°1', 'nom' => 'École Koulouba A', 'statut' => 'public', 'type' => 'classique']);
    }

    private function donneesOev(array $surcharge = []): array
    {
        return $surcharge + [
            'nom' => 'OUEDRAOGO', 'prenom' => 'Awa', 'sexe' => 'F', 'date_naissance' => now()->subYears(9)->toDateString(),
            'mere_vivante' => 'oui', 'pere_vivant' => 'non', 'a_acte_naissance' => '0',
            'tuteur_lien' => 'oncle_tante', 'tuteur_sexe' => 'M', 'tuteur_a_cnib' => '0', 'tuteur_pret_continuer' => '1', 'handicap' => '0',
            'nom_tuteur' => 'SAWADOGO', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '70 00 00 00',
            'situation_scolaire' => 'scolarise', 'niveau_etude' => 'primaire', 'systeme_educatif' => 'classique',
            'type_etablissement' => 'public', 'classe' => 'CE2', 'performance_scolaire' => 'bonnes',
            'region_id' => $this->ouagadougou->province->region_id, 'province_id' => $this->ouagadougou->province_id, 'commune_id' => $this->ouagadougou->id,
        ];
    }

    public function test_le_seeder_rattache_les_ecoles_a_toutes_les_communes_du_fichier(): void
    {
        $this->seed(EcoleSeeder::class);

        $this->assertSame(20895, Ecole::count(), 'Toutes les lignes du fichier (20 896 avec l’en-tête)');
        $this->assertSame(0, Ecole::where('nom', 'like', '%Ã%')->count(), 'Aucun nom mal encodé');
        $this->assertTrue(Commune::where('nom', 'Bagassi')->first()->ecoles()->where('nom', 'BAGASSI A')->exists());

        // Relancer l'import ne crée pas de doublons
        $total = Ecole::count();
        $this->seed(EcoleSeeder::class);
        $this->assertSame($total, Ecole::count());
    }

    public function test_l_administrateur_gere_les_ecoles_dans_les_parametres(): void
    {
        $this->actingAs($this->admin)->get(route('parametres.edit'))->assertOk()->assertSee(route('parametres.ecoles.index'));
        $this->get(route('parametres.ecoles.index'))->assertOk();

        $this->post(route('parametres.ecoles.store'), [
            'province_id' => $this->ouagadougou->province_id, 'commune_id' => $this->ouagadougou->id,
            'ceb' => 'OUAGA N°3', 'nom' => 'École Wemtenga', 'statut' => 'prive', 'type' => 'franco_arabe',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $ecole = Ecole::where('nom', 'École Wemtenga')->firstOrFail();
        $this->assertSame(['prive', 'franco_arabe'], [$ecole->statut, $ecole->type]);

        $this->get(route('parametres.ecoles.index', ['q' => 'Wemtenga']))->assertOk()->assertSee('École Wemtenga');
        $this->put(route('parametres.ecoles.update', $ecole), [
            'province_id' => $this->ouagadougou->province_id, 'commune_id' => $this->ouagadougou->id, 'ceb' => 'OUAGA N°3', 'nom' => 'École Wemtenga B',
        ])->assertSessionHasNoErrors();
        $this->assertSame('École Wemtenga B', $ecole->fresh()->nom);

        $this->delete(route('parametres.ecoles.destroy', $ecole))->assertRedirect();
        $this->assertModelMissing($ecole);
    }

    public function test_les_ecoles_sont_reservees_aux_gestionnaires_des_parametres(): void
    {
        $this->actingAs($this->dp)->get(route('parametres.ecoles.index'))->assertAccesRefuse();
    }

    public function test_l_import_excel_ajoute_puis_met_a_jour_les_ecoles(): void
    {
        $fichier = function (array $lignes): UploadedFile {
            $classeur = new Spreadsheet;
            $classeur->getActiveSheet()->fromArray([['region', 'province', 'commune', 'CEB', 'Ecole', 'statut', 'Type'], ...$lignes]);
            $chemin = tempnam(sys_get_temp_dir(), 'ecoles') . '.xlsx';
            (new Xlsx($classeur))->save($chemin);

            return new UploadedFile($chemin, 'ecoles.xlsx', null, null, true);
        };

        $this->actingAs($this->admin)->post(route('parametres.ecoles.import'), ['fichier' => $fichier([
            ['KADIOGO', 'KADIOGO', 'OUAGADOUGOU', 'OUAGA N°1', 'KOULOUBA A', 'PUBLIC', 'CLASSIQUE'],
            ['KADIOGO', 'KADIOGO', 'OUAGADOUGOU', 'OUAGA N°1', 'KOULOUBA A', 'PUBLIC', 'CLASSIQUE'],
            ['NAZINON', 'ZOUNDWEOGO', 'GON-BOUSSOUGOU', 'Gomboussougou', 'BANGRIN', 'PRIVE', 'FRANCO-ARABE'],
            ['X', 'Y', 'COMMUNE INCONNUE', 'Z', 'ECOLE', 'PUBLIC', 'CLASSIQUE'],
        ])])->assertSessionHas('success');

        $this->assertSame(3, Ecole::count(), 'Les deux lignes KOULOUBA A sont gardées, la commune inconnue est ignorée');
        $this->assertSame('franco_arabe', Ecole::where('nom', 'BANGRIN')->value('type'));

        // Réimport : pas de doublon, le statut de la première KOULOUBA A est mis à jour
        $this->post(route('parametres.ecoles.import'), ['fichier' => $fichier([
            ['KADIOGO', 'KADIOGO', 'OUAGADOUGOU', 'OUAGA N°1', 'KOULOUBA A', 'PRIVE', 'CLASSIQUE'],
        ])])->assertSessionHas('success');
        $this->assertSame(3, Ecole::count());
        $this->assertSame(['prive', 'public'], Ecole::where('nom', 'KOULOUBA A')->orderBy('id')->pluck('statut')->all());
    }

    public function test_le_formulaire_oev_charge_les_ecoles_de_la_commune(): void
    {
        $ecole = $this->ecole();
        $this->ecole(['commune_id' => Commune::where('nom', 'Saaba')->value('id'), 'nom' => 'École de Saaba']);

        $this->actingAs($this->dp)->getJson(route('localites.ecoles-commune', $this->ouagadougou))
            ->assertOk()->assertExactJson([['id' => $ecole->id, 'nom' => 'École Koulouba A', 'ceb' => 'OUAGA N°1', 'statut' => 'public', 'type' => 'classique']]);
    }

    public function test_l_oev_choisit_son_ecole_dans_la_liste_de_sa_commune(): void
    {
        $ecole = $this->ecole();

        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['ecole_id' => $ecole->id]))->assertSessionHasNoErrors();
        $oev = Oev::firstOrFail();
        $this->assertSame([$ecole->id, 'École Koulouba A'], [$oev->ecole_id, $oev->etablissement_actuel]);

        // Une école est protégée tant qu'un dossier l'a choisie
        $this->actingAs($this->admin)->delete(route('parametres.ecoles.destroy', $ecole))->assertSessionHas('error');
        $this->assertModelExists($ecole);
    }

    public function test_une_ecole_d_une_autre_commune_est_refusee(): void
    {
        $ecole = $this->ecole(['commune_id' => Commune::where('nom', 'Saaba')->value('id')]);

        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['ecole_id' => $ecole->id]))->assertSessionHasErrors('ecole_id');
    }

    public function test_l_ecole_de_l_annee_precedente_est_choisie_dans_la_liste(): void
    {
        $ecole = $this->ecole(['nom' => 'École Koulouba B']);
        $donnees = $this->donneesOev(['ecole_id' => 'autre', 'etablissement_actuel' => 'Lycée Zinda', 'classe_precedente' => 'CE1', 'moyenne_annuelle' => '6', 'appreciation' => 'admis']);

        $this->actingAs($this->dp)->post(route('oevs.store'), $donnees + ['ecole_precedente_id' => $ecole->id])->assertSessionHasNoErrors();
        $oev = Oev::firstOrFail();
        $this->assertSame([$ecole->id, 'École Koulouba B', 'CE1'], [$oev->ecole_precedente_id, $oev->etablissement_precedent, $oev->classe_precedente]);

        // « Autre » : le nom est à saisir
        $this->post(route('oevs.store'), $donnees + ['ecole_precedente_id' => 'autre'])->assertSessionHasErrors('etablissement_precedent');
        // École d'une autre commune : refusée
        $autre = $this->ecole(['commune_id' => Commune::where('nom', 'Saaba')->value('id'), 'nom' => 'École de Saaba']);
        $this->post(route('oevs.store'), $donnees + ['ecole_precedente_id' => $autre->id])->assertSessionHasErrors('ecole_precedente_id');
    }

    public function test_un_etablissement_hors_liste_est_saisi(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['ecole_id' => 'autre']))->assertSessionHasErrors('etablissement_actuel');

        $this->post(route('oevs.store'), $this->donneesOev(['ecole_id' => 'autre', 'etablissement_actuel' => 'Lycée Zinda']))->assertSessionHasNoErrors();
        $oev = Oev::firstOrFail();
        $this->assertSame([null, 'Lycée Zinda'], [$oev->ecole_id, $oev->etablissement_actuel]);
    }
}
