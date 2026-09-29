<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Province;
use App\Models\QuickLink;
use App\Models\Region;
use App\Models\Signalement;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\QuickLinkSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SiteSettingSeeder;
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

        $this->actingAs($agent)->get(route('localites.regions.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('parametres.edit'))->assertForbidden();
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
