<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\ListeReference;
use App\Models\Oev;
use App\Models\User;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Paramètres > listes de choix de la fiche OEV (groupes de population, tuteur, conditions de vie, vulnérabilités). */
class ListeReferenceTest extends TestCase
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

    private function donneesOev(array $surcharge = []): array
    {
        return $surcharge + [
            'nom' => 'OUEDRAOGO', 'prenom' => 'Awa', 'sexe' => 'F', 'date_naissance' => now()->subYears(9)->toDateString(),
            'mere_vivante' => 'oui', 'pere_vivant' => 'non', 'a_acte_naissance' => '0',
            'tuteur_lien' => 'oncle_tante', 'tuteur_sexe' => 'M', 'tuteur_a_cnib' => '0', 'tuteur_pret_continuer' => '1', 'handicap' => '0',
            'nom_tuteur' => 'SAWADOGO', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '70 00 00 00',
            'situation_scolaire' => 'non_scolarise', 'raison_non_scolarisation' => 'financieres', 'souhait_avenir' => 'ecole',
            'region_id' => $this->ouagadougou->province->region_id, 'province_id' => $this->ouagadougou->province_id, 'commune_id' => $this->ouagadougou->id,
        ];
    }

    private function element(string $liste, string $code): ListeReference
    {
        return ListeReference::where('liste', $liste)->where('code', $code)->firstOrFail();
    }

    public function test_les_listes_sont_initialisees_avec_les_valeurs_d_origine(): void
    {
        $this->assertSame(Oev::GROUPES_POPULATION, Oev::groupesPopulation());
        $this->assertSame(Oev::LIENS_TUTEUR, Oev::liensTuteur());
        $this->assertSame(Oev::LIEUX_DE_VIE, Oev::lieuxDeVie());
        $this->assertSame(Oev::VULNERABILITES, Oev::vulnerabilites());
        $this->assertTrue($this->element('vulnerabilites', 'orphelin')->systeme);
        $this->assertFalse($this->element('vulnerabilites', 'separe')->systeme);
    }

    public function test_les_listes_sont_dans_le_menu_parametres(): void
    {
        $page = $this->actingAs($this->admin)->get(route('parametres.edit'))->assertOk();
        foreach (array_keys(ListeReference::listes()) as $liste) {
            $page->assertSee(route('parametres.listes.index', $liste));
            $this->get(route('parametres.listes.index', $liste))->assertOk();
        }
        $page->assertSee(route('parametres.ecoles.index'))->assertSee(route('localites.regions.index'));

        $this->actingAs($this->dp)->get(route('parametres.listes.index', 'vulnerabilites'))->assertAccesRefuse();
    }

    public function test_un_choix_ajoute_est_propose_dans_la_fiche_oev(): void
    {
        $this->actingAs($this->admin)->post(route('parametres.listes.store', 'lieux-de-vie'), ['libelle' => 'Chez un maître coranique'])->assertSessionHasNoErrors();
        $this->assertSame('Chez un maître coranique', Oev::lieuxDeVie()['chez_un_maitre_coranique']);

        $this->actingAs($this->dp)->get(route('oevs.create'))->assertSee('Chez un maître coranique');
        $this->post(route('oevs.store'), $this->donneesOev(['lieu_de_vie' => 'chez_un_maitre_coranique']))->assertSessionHasNoErrors();
        $this->assertSame('chez_un_maitre_coranique', Oev::firstOrFail()->lieu_de_vie);
    }

    public function test_un_choix_desactive_n_est_plus_propose_mais_reste_affiche(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['groupe_population' => 'apatride']))->assertSessionHasNoErrors();
        $oev = Oev::firstOrFail();

        $apatride = $this->element('groupes-population', 'apatride');
        $this->actingAs($this->admin)->put(route('parametres.listes.update', ['groupes-population', $apatride]), ['libelle' => 'Apatride', 'ordre' => 7])->assertSessionHasNoErrors();
        $this->assertFalse($apatride->fresh()->actif);

        $this->actingAs($this->dp)->post(route('oevs.store'), $this->donneesOev(['groupe_population' => 'apatride']))->assertSessionHasErrors('groupe_population');
        $this->assertArrayNotHasKey('apatride', Oev::groupesPopulation(true));
        $this->get(route('oevs.show', $oev))->assertSee('Apatride');

        // Choisi dans un dossier : il ne peut pas être supprimé
        $this->actingAs($this->admin)->delete(route('parametres.listes.destroy', ['groupes-population', $apatride]))->assertSessionHas('error');
        $this->assertModelExists($apatride);
    }

    public function test_un_choix_systeme_garde_son_code_et_reste_actif(): void
    {
        $mere = $this->element('liens-tuteur', 'mere');
        $this->actingAs($this->admin)->put(route('parametres.listes.update', ['liens-tuteur', $mere]), ['libelle' => 'La mère', 'ordre' => 2])->assertSessionHasNoErrors();
        $this->assertSame(['La mère', true, 'mere'], [$mere->fresh()->libelle, $mere->fresh()->actif, $mere->fresh()->code]);

        $this->delete(route('parametres.listes.destroy', ['liens-tuteur', $mere]))->assertSessionHas('error');
        $this->assertModelExists($mere);
    }

    public function test_un_choix_inutilise_peut_etre_supprime(): void
    {
        $separe = $this->element('vulnerabilites', 'separe');
        $this->actingAs($this->admin)->delete(route('parametres.listes.destroy', ['vulnerabilites', $separe]))->assertSessionHas('success');
        $this->assertModelMissing($separe);
        $this->assertArrayNotHasKey('separe', Oev::vulnerabilites());
    }
}
