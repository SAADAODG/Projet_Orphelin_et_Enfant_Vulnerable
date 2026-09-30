<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Province;
use App\Models\QuickLink;
use App\Models\Region;
use App\Models\Signalement;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\QuickLinkSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SiteSettingSeeder;
use Database\Seeders\VillageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaliteParametreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrateur');
    }

    public function test_les_seeders_importent_le_decoupage_administratif(): void
    {
        $this->seed([LocaliteSeeder::class, CommuneSeeder::class]);

        $this->assertSame(17, Region::count());
        $this->assertSame(47, Province::count());
        $this->assertGreaterThan(300, Commune::count());
        $this->assertTrue(Province::where('nom', 'Kadiogo')->first()->communes()->where('nom', 'Ouagadougou')->exists());
    }

    public function test_le_seeder_importe_les_villages_de_toutes_les_communes(): void
    {
        $this->seed([LocaliteSeeder::class, CommuneSeeder::class, VillageSeeder::class]);

        $this->assertGreaterThan(10000, Village::count());
        $this->assertSame(Commune::count(), Village::distinct()->count('commune_id'), 'Chaque commune a ses villages');
        $this->assertSame(0, Village::where('nom', 'like', '%Ã%')->count(), 'Aucun nom mal encodé');
        $this->assertTrue(Commune::where('nom', 'Dédougou')->first()->villages()->where('nom', 'Bokuy')->exists());

        // Relancer l'import ne crée pas de doublons
        $total = Village::count();
        $this->seed(VillageSeeder::class);
        $this->assertSame($total, Village::count());
    }

    public function test_les_pages_localites_et_parametres_s_affichent(): void
    {
        $this->seed([LocaliteSeeder::class, CommuneSeeder::class, SiteSettingSeeder::class, QuickLinkSeeder::class, ServiceSeeder::class]);

        foreach (['localites.regions.index', 'localites.provinces.index', 'localites.communes.index',
            'localites.regions.create', 'localites.provinces.create', 'localites.communes.create', 'parametres.edit'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
    }

    public function test_un_agent_sans_permission_est_refuse(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent DGFE');

        $this->actingAs($agent)->get(route('localites.regions.index'))->assertAccesRefuse();
        $this->actingAs($agent)->get(route('parametres.edit'))->assertAccesRefuse();
    }

    public function test_crud_region_province_commune(): void
    {
        $this->actingAs($this->admin)
            ->post(route('localites.regions.store'), ['nom' => 'Kadiogo-Test', 'ancien_nom' => 'Centre'])
            ->assertRedirect(route('localites.regions.index'));
        $region = Region::where('nom', 'Kadiogo-Test')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('localites.provinces.store'), ['region_id' => $region->id, 'nom' => 'Province-Test'])
            ->assertRedirect(route('localites.provinces.index'));
        $province = Province::where('nom', 'Province-Test')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('localites.communes.store'), ['province_id' => $province->id, 'nom' => 'Commune-Test'])
            ->assertRedirect(route('localites.communes.index'));

        // Une région qui a encore des provinces ne peut pas être supprimée.
        $this->actingAs($this->admin)->delete(route('localites.regions.destroy', $region))->assertSessionHas('error');
        $this->assertModelExists($region);
    }

    public function test_crud_village(): void
    {
        $this->seed([LocaliteSeeder::class, CommuneSeeder::class]);
        $commune = Commune::where('nom', 'Ouagadougou')->with('province')->firstOrFail();
        $autreProvince = Province::whereKeyNot($commune->province_id)->firstOrFail();
        $localite = ['region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id];

        foreach (['localites.villages.index', 'localites.villages.create'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }

        $this->post(route('localites.villages.store'), $localite + ['nom' => 'Secteur 12'])
            ->assertRedirect(route('localites.villages.index', ['commune_id' => $commune->id]));
        $village = Village::where('nom', 'Secteur 12')->firstOrFail();
        $this->assertSame($commune->id, $village->commune_id);

        // Doublon dans la même commune, ou commune qui ne correspond pas à la province : refusés
        $this->post(route('localites.villages.store'), $localite + ['nom' => 'Secteur 12'])->assertSessionHasErrors('nom');
        $this->post(route('localites.villages.store'), ['province_id' => $autreProvince->id, 'commune_id' => $commune->id, 'nom' => 'X'])
            ->assertSessionHasErrors('commune_id');

        $this->get(route('localites.villages.index', ['commune_id' => $commune->id]))->assertSee('Secteur 12');
        $this->get(route('localites.villages.edit', $village))->assertOk()->assertSee('Secteur 12');
        $this->put(route('localites.villages.update', $village), $localite + ['nom' => 'Secteur 13'])->assertSessionHasNoErrors();
        $this->assertSame('Secteur 13', $village->refresh()->nom);

        // Une commune qui a des villages ne peut pas être supprimée
        $this->delete(route('localites.communes.destroy', $commune))->assertSessionHas('error');
        $this->assertModelExists($commune);

        $this->delete(route('localites.villages.destroy', $village))->assertSessionHas('success');
        $this->assertModelMissing($village);
    }

    public function test_le_signalement_public_utilise_les_localites_de_la_base(): void
    {
        $this->seed([LocaliteSeeder::class, CommuneSeeder::class]);
        $commune = Commune::where('nom', 'Bobo-Dioulasso')->firstOrFail();

        $this->get(route('public.signaler'))->assertOk()->assertSee('Bobo-Dioulasso');

        $donnees = [
            'enfant_nom' => 'SANOU', 'enfant_prenom' => 'Moussa', 'enfant_age' => 9,
            'vulnerabilites' => ['orphelin'],
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'localite' => 'Secteur 22',
            'declarant_nom' => 'TRAORE', 'declarant_prenom' => 'Awa', 'declarant_telephone' => '70 00 00 00',
            'declarant_adresse' => 'Bobo', 'declarant_profession' => 'Enseignante', 'declarant_lien' => 'parent',
        ];

        // Province qui n'appartient pas à la région choisie : refusée.
        $autreProvince = Province::where('region_id', '!=', $commune->province->region_id)->firstOrFail();
        $this->post(route('public.signaler.store'), ['province_id' => $autreProvince->id] + $donnees)->assertSessionHasErrors('province_id');

        $this->post(route('public.signaler.store'), $donnees)->assertSessionHasNoErrors()->assertRedirect(route('public.signaler.merci'));
        $signalement = Signalement::firstOrFail();
        $this->assertSame('Bobo-Dioulasso, Houet (Guiriko)', $signalement->localiteComplete());
    }

    public function test_les_parametres_generaux_alimentent_le_site_public(): void
    {
        $this->seed([SiteSettingSeeder::class, QuickLinkSeeder::class, ServiceSeeder::class]);

        $this->actingAs($this->admin)->put(route('parametres.update'), [
            'nom_site' => 'Programme OEV',
            'structure_nom' => 'DGFE',
            'ministere_tutelle' => 'Ministère Test',
            'police_admin' => 'roboto',
            'police_public' => 'open-sans',
            'contact_telephone' => '+226 11 22 33 44',
        ])->assertRedirect(route('parametres.edit'));

        $this->assertSame('roboto', SiteSetting::current()->police_admin);

        $this->actingAs($this->admin)->post(route('quick-links.store'), ['libelle' => 'Lien test', 'url' => '/a-propos']);
        $this->assertTrue(QuickLink::where('libelle', 'Lien test')->exists());

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('MINISTÈRE TEST')
            ->assertSee('+226 11 22 33 44')
            ->assertSee('Lien test')
            ->assertSee('assets/fonts/open-sans/open-sans.css');
    }
}
