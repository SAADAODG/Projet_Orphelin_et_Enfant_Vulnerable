<?php

namespace Tests\Feature;

use App\Models\AppuiPartenaire;
use App\Models\Commune;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\User;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Paramètres > natures d'appui du parrainage. */
class NatureAppuiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);
        $province = Commune::where('nom', 'Ouagadougou')->firstOrFail()->province;

        $this->admin = User::factory()->create()->assignRole('administrateur');
        $this->dp = User::factory()->create(['region_id' => $province->region_id, 'province_id' => $province->id])->assignRole('DP');
    }

    public function test_seul_l_administrateur_parametre_les_natures(): void
    {
        $this->actingAs($this->dp)->get(route('parametres.natures-appui.index'))->assertAccesRefuse();
        $this->actingAs($this->admin)->get(route('parametres.natures-appui.index'))->assertOk()->assertSee('Santé')->assertSee('Natures d’appui');
    }

    public function test_une_nature_ajoutee_est_proposee_puis_desactivee(): void
    {
        $this->actingAs($this->admin)->post(route('parametres.natures-appui.store'), ['libelle' => 'Kit d’hygiène'])->assertRedirect();
        $nature = NatureAppui::where('libelle', 'Kit d’hygiène')->sole();
        $this->assertSame('kit_dhygiene', $nature->code);
        $this->assertTrue(NatureAppui::actives()->whereKey($nature->id)->exists());

        // Libellé déjà pris
        $this->actingAs($this->admin)->post(route('parametres.natures-appui.store'), ['libelle' => 'Santé'])->assertSessionHasErrors('libelle');

        $this->actingAs($this->admin)->put(route('parametres.natures-appui.update', $nature), ['libelle' => 'Hygiène', 'ordre' => 3])->assertRedirect();
        $nature->refresh();
        $this->assertSame('Hygiène', $nature->libelle);
        $this->assertFalse($nature->actif);
        $this->assertFalse(NatureAppui::actives()->whereKey($nature->id)->exists());
    }

    public function test_les_natures_systeme_restent_proposees_et_les_natures_utilisees_ne_se_suppriment_pas(): void
    {
        $scolaire = NatureAppui::where('code', 'scolaire')->sole();
        $this->actingAs($this->admin)->put(route('parametres.natures-appui.update', $scolaire), ['libelle' => 'Études', 'ordre' => 9])->assertRedirect();
        $scolaire->refresh();
        $this->assertSame('Scolaire', $scolaire->libelle, 'Libellé repris des types d’appui des sessions');
        $this->assertTrue($scolaire->actif);
        $this->assertSame(9, (int) $scolaire->ordre);
        $this->actingAs($this->admin)->delete(route('parametres.natures-appui.destroy', $scolaire))->assertSessionHas('error');

        $sante = NatureAppui::where('code', 'sante')->sole();
        $parrain = Parrain::create(['type' => 'ong', 'nom' => 'ONG', 'actif' => true]);
        $oev = Oev::create([
            'nom' => 'ENFANT', 'prenom' => 'Awa', 'sexe' => 'F', 'date_naissance' => now()->subYears(10)->toDateString(),
            'statut' => 'orphelin_pere', 'handicap' => false, 'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'region_id' => $this->dp->region_id, 'province_id' => $this->dp->province_id,
            'commune_id' => Commune::where('province_id', $this->dp->province_id)->value('id'),
            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(), 'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
        ]);
        AppuiPartenaire::create(['oev_id' => $oev->id, 'parrain_id' => $parrain->id, 'annee' => '2026-2027', 'nature_appui_id' => $sante->id]);
        $this->actingAs($this->admin)->delete(route('parametres.natures-appui.destroy', $sante))->assertSessionHas('error');
        $this->assertModelExists($sante);

        $vestimentaire = NatureAppui::where('code', 'vestimentaire')->sole();
        $this->actingAs($this->admin)->delete(route('parametres.natures-appui.destroy', $vestimentaire))->assertRedirect()->assertSessionHas('success');
        $this->assertModelMissing($vestimentaire);
    }
}
