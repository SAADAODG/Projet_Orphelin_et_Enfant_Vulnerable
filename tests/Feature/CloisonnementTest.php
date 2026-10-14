<?php

namespace Tests\Feature;

use App\Models\AppuiPartenaire;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cloisonnement par zone (CloisonneParZone) : chaque agent ne voit et ne touche que sa zone. */
class CloisonnementTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;
    private Province $p1;
    private Province $p2;
    private Province $ailleurs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class]);

        $this->region = Region::has('provinces', '>=', 2)->firstOrFail();
        [$this->p1, $this->p2] = $this->region->provinces()->orderBy('id')->take(2)->get()->all();
        $this->ailleurs = Province::where('region_id', '!=', $this->region->id)->firstOrFail();
    }

    private function dossier(string $nom, Province $province, string $etat = Oev::ETAT_INTEGRE): Oev
    {
        return Oev::create([
            'nom' => $nom, 'prenom' => 'Test', 'sexe' => 'F', 'date_naissance' => '2015-03-01', 'statut' => 'vulnerable',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'region_id' => $province->region_id, 'province_id' => $province->id,
            'numero_dossier' => Oev::genererNumeroDossier(), 'statut_dossier' => $etat,
            'code' => $etat === Oev::ETAT_INTEGRE ? Oev::genererCode() : null, 'soumis_at' => now(),
        ]);
    }

    public function test_un_dr_sans_region_ne_voit_aucun_dossier(): void
    {
        $this->dossier('SOUMIS', $this->p1, Oev::ETAT_SOUMIS);
        $this->dossier('INTEGRE', $this->p1);
        $drSansRegion = User::factory()->create()->assignRole('DR');

        $this->actingAs($drSansRegion)->get(route('oevs.validation', ['etat' => 'tous']))->assertOk()->assertDontSee('SOUMIS');
        $this->get(route('oevs.liste'))->assertOk()->assertDontSee('INTEGRE');
        $this->get(route('oevs.sortants'))->assertOk();
    }

    public function test_le_dr_filtre_la_validation_par_province_de_sa_region_seulement(): void
    {
        $this->dossier('PROVINCE_UN', $this->p1, Oev::ETAT_SOUMIS);
        $this->dossier('PROVINCE_DEUX', $this->p2, Oev::ETAT_SOUMIS);
        $this->dossier('AUTRE_REGION', $this->ailleurs, Oev::ETAT_SOUMIS);
        $dr = User::factory()->create(['region_id' => $this->region->id])->assignRole('DR');

        $this->actingAs($dr)->get(route('oevs.validation', ['etat' => 'tous']))
            ->assertOk()->assertSee('PROVINCE_UN')->assertSee('PROVINCE_DEUX')->assertDontSee('AUTRE_REGION')->assertSee('Toutes les provinces');
        $this->get(route('oevs.validation', ['etat' => 'tous', 'region_id' => $this->region->id, 'province_id' => $this->p2->id]))
            ->assertOk()->assertDontSee('PROVINCE_UN')->assertSee('PROVINCE_DEUX');
        // Région hors de sa zone demandée dans l'adresse : ignorée, il reste dans sa région
        $this->get(route('oevs.validation', ['etat' => 'tous', 'region_id' => $this->ailleurs->region_id]))
            ->assertOk()->assertSee('PROVINCE_UN')->assertDontSee('AUTRE_REGION');
    }

    public function test_le_niveau_central_filtre_l_integration_par_region(): void
    {
        $this->dossier('VALIDE_ICI', $this->p1, Oev::ETAT_VALIDE);
        $this->dossier('VALIDE_AILLEURS', $this->ailleurs, Oev::ETAT_VALIDE);
        $central = User::factory()->create()->assignRole('agent DGFE');

        $this->actingAs($central)->get(route('oevs.integration', ['region_id' => $this->region->id]))
            ->assertOk()->assertSee('VALIDE_ICI')->assertDontSee('VALIDE_AILLEURS')->assertSee('Toutes les régions');
    }

    public function test_la_constitution_separe_les_dossiers_en_cours_et_les_integres(): void
    {
        $this->dossier('EN_CONSTITUTION', $this->p1, Oev::ETAT_BROUILLON);
        $this->dossier('REJETE', $this->p1, Oev::ETAT_REJETE);
        $this->dossier('DEVENU_OEV', $this->p1);
        $dp = User::factory()->create(['region_id' => $this->region->id, 'province_id' => $this->p1->id])->assignRole('DP');

        $this->actingAs($dp)->get(route('oevs.index', ['etat' => 'tous']))
            ->assertOk()->assertSee('EN_CONSTITUTION')->assertSee('REJETE')->assertDontSee('DEVENU_OEV')
            ->assertSee('Dossiers en cours')->assertSee('Dossiers intégrés');
        $this->get(route('oevs.index', ['vue' => 'integres']))
            ->assertOk()->assertSee('DEVENU_OEV')->assertDontSee('EN_CONSTITUTION')->assertDontSee('REJETE');
    }

    public function test_un_appui_hors_de_la_zone_du_dp_n_est_pas_accessible(): void
    {
        $dp = User::factory()->create(['region_id' => $this->region->id, 'province_id' => $this->p1->id])->assignRole('DP');
        $appui = AppuiPartenaire::create([
            'oev_id' => $this->dossier('LOINTAIN', $this->ailleurs)->id,
            'parrain_id' => Parrain::create(['type' => 'ong', 'nom' => 'ONG'])->id,
            'annee' => '2026-2027', 'nature_appui_id' => NatureAppui::value('id'),
        ]);

        $this->actingAs($dp)->get(route('parrainage.appuis.edit', $appui))->assertRedirect();
        $this->put(route('parrainage.appuis.update', $appui), ['annee' => '2027-2028'])->assertRedirect();
        $this->assertSame('2026-2027', $appui->refresh()->annee);
    }
}
