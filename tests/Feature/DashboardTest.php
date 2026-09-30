<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Oev;
use App\Models\Province;
use App\Models\Region;
use App\Models\Signalement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** Région Centre : provinces Kadiogo et Bazèga ; région Guiriko : province Houet. */
    private Region $centre;
    private Region $guiriko;
    private Province $kadiogo;
    private Province $bazega;
    private Province $houet;
    private Commune $ouagadougou;
    private Commune $kombissiri;
    private Commune $boboDioulasso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->centre = Region::create(['nom' => 'Centre']);
        $this->guiriko = Region::create(['nom' => 'Guiriko']);
        $this->kadiogo = Province::create(['region_id' => $this->centre->id, 'nom' => 'Kadiogo']);
        $this->bazega = Province::create(['region_id' => $this->centre->id, 'nom' => 'Bazèga']);
        $this->houet = Province::create(['region_id' => $this->guiriko->id, 'nom' => 'Houet']);
        $this->ouagadougou = Commune::create(['province_id' => $this->kadiogo->id, 'nom' => 'Ouagadougou']);
        $this->kombissiri = Commune::create(['province_id' => $this->bazega->id, 'nom' => 'Kombissiri']);
        $this->boboDioulasso = Commune::create(['province_id' => $this->houet->id, 'nom' => 'Bobo-Dioulasso']);

        // Kadiogo : 2 en constitution (dont 1 non conforme), 1 soumis ; Bazèga : 1 intégré ; Houet : 1 validé, 1 intégré
        $this->dossier($this->ouagadougou, Oev::ETAT_BROUILLON);
        $this->dossier($this->ouagadougou, Oev::ETAT_NON_CONFORME);
        $this->dossier($this->ouagadougou, Oev::ETAT_SOUMIS);
        $this->dossier($this->kombissiri, Oev::ETAT_INTEGRE);
        $this->dossier($this->boboDioulasso, Oev::ETAT_VALIDE);
        $this->dossier($this->boboDioulasso, Oev::ETAT_INTEGRE, ['sexe' => 'M']);

        $this->signalement($this->ouagadougou);
        $this->signalement($this->boboDioulasso);
        $this->signalement($this->boboDioulasso);
    }

    private function dossier(Commune $commune, string $etat, array $attributs = []): Oev
    {
        $integre = $etat === Oev::ETAT_INTEGRE;

        return Oev::create($attributs + [
            'nom' => 'ENFANT', 'prenom' => Str::random(6), 'sexe' => 'F', 'date_naissance' => '2015-03-01',
            'statut' => 'orphelin_pere', 'handicap' => false, 'systeme_educatif' => 'classique',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_actuel' => 'École A', 'type_etablissement' => 'public', 'classe' => 'CM1', 'frais_scolarite' => 0,
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'numero_dossier' => Oev::genererNumeroDossier(),
            'statut_dossier' => $etat,
            'code' => $integre ? Oev::genererCode() : null,
            'integre_at' => $integre ? now() : null,
            'soumis_at' => $etat === Oev::ETAT_BROUILLON ? null : now(),
        ]);
    }

    private function signalement(Commune $commune): void
    {
        Signalement::forceCreate([
            'recepisse' => Signalement::genererRecepisse(),
            'enfant_nom' => 'ENFANT', 'enfant_prenom' => 'Test', 'enfant_age' => 9, 'vulnerabilite' => 'orphelin',
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'localite' => 'Secteur 1',
            'declarant_nom' => 'DECLARANT', 'declarant_prenom' => 'Awa', 'declarant_telephone' => '70000000',
            'declarant_adresse' => 'Secteur 1', 'declarant_profession' => 'Commerçante', 'declarant_lien' => 'voisin',
            'statut' => Signalement::EN_ATTENTE,
        ]);
    }

    private function utilisateur(string $role, array $attributs = []): User
    {
        return User::factory()->create($attributs)->assignRole($role);
    }

    /** Nombre total de dossiers dans le circuit affiché. */
    private function totalDossiers(TestResponse $reponse): int
    {
        return $reponse->viewData('circuit')->sum('total');
    }

    private function indicateur(TestResponse $reponse, string $libelle): int
    {
        return collect($reponse->viewData('indicateurs'))->firstWhere('libelle', $libelle)['valeur'];
    }

    public function test_le_dp_ne_voit_que_sa_province(): void
    {
        $dp = $this->utilisateur('DP', ['region_id' => $this->centre->id, 'province_id' => $this->kadiogo->id]);

        $reponse = $this->actingAs($dp)->get(route('dashboard'))->assertOk()
            ->assertSee('Direction provinciale')
            ->assertSee('Province : Kadiogo')
            ->assertSee('Répartition par commune');

        $this->assertSame(3, $this->totalDossiers($reponse));
        $this->assertSame(1, $this->indicateur($reponse, 'En constitution'));
        $this->assertSame(1, $this->indicateur($reponse, 'Non conformes'));
        $this->assertSame(1, $this->indicateur($reponse, 'Chez le DR'));
        $this->assertSame(1, $reponse->viewData('signalements')->sum());
        $this->assertSame(['Ouagadougou'], $reponse->viewData('repartition')->pluck('nom')->all());
        // Les dossiers à reprendre commencent par le non conforme
        $this->assertSame(Oev::ETAT_NON_CONFORME, $reponse->viewData('aTraiter')->first()->statut_dossier);
    }

    public function test_le_dr_voit_sa_region_ventilee_par_province(): void
    {
        $dr = $this->utilisateur('DR', ['region_id' => $this->centre->id]);

        $reponse = $this->actingAs($dr)->get(route('dashboard'))->assertOk()
            ->assertSee('Direction régionale')
            ->assertSee('Région : Centre')
            ->assertDontSee('Bobo-Dioulasso');

        // Le dossier encore en constitution chez le DP n'est pas visible du DR, il est seulement compté « chez les DP »
        $this->assertSame(3, $this->totalDossiers($reponse));
        $this->assertArrayNotHasKey(Oev::ETAT_BROUILLON, $reponse->viewData('circuit')->all());
        $this->assertSame(1, $this->indicateur($reponse, 'À vérifier'));
        $this->assertSame(2, $this->indicateur($reponse, 'Chez les DP'));
        $this->assertEqualsCanonicalizing(['Kadiogo', 'Bazèga'], $reponse->viewData('repartition')->pluck('nom')->all());
        $this->assertSame(1, $reponse->viewData('oevIntegres')['total']);
    }

    public function test_le_central_voit_tout_le_pays_et_peut_filtrer(): void
    {
        $central = $this->utilisateur('agent DGFE');

        $reponse = $this->actingAs($central)->get(route('dashboard'))->assertOk()
            ->assertSee('Niveau central')
            ->assertSee('Tout le pays');
        // Seuls les dossiers validés par un DR (ou au-delà) lui sont visibles ; pas les signalements
        $this->assertSame(3, $this->totalDossiers($reponse));
        $this->assertSame(1, $this->indicateur($reponse, 'À intégrer'));
        $this->assertSame(2, $this->indicateur($reponse, 'OEV intégrés'));
        $this->assertSame(0, $this->indicateur($reponse, 'Nouvelles plaintes'));
        $this->assertNull($reponse->viewData('signalements'));
        $this->assertNotNull($reponse->viewData('plaintes'));
        $this->assertEqualsCanonicalizing(['Centre', 'Guiriko'], $reponse->viewData('repartition')->pluck('nom')->all());

        // Filtre par région, puis par province
        $parRegion = $this->get(route('dashboard', ['region_id' => $this->guiriko->id]))->assertSee('Région : Guiriko');
        $this->assertSame(2, $this->totalDossiers($parRegion));
        $parProvince = $this->get(route('dashboard', ['region_id' => $this->centre->id, 'province_id' => $this->bazega->id]))->assertSee('Province : Bazèga');
        $this->assertSame(1, $this->totalDossiers($parProvince));

        // Une province qui n'est pas dans la région choisie est ignorée
        $incoherent = $this->get(route('dashboard', ['region_id' => $this->guiriko->id, 'province_id' => $this->kadiogo->id]));
        $this->assertSame('Région : Guiriko', $incoherent->viewData('perimetre')->libelle());
    }

    public function test_le_dp_et_le_dr_ne_peuvent_pas_elargir_leur_perimetre(): void
    {
        $dp = $this->utilisateur('DP', ['region_id' => $this->centre->id, 'province_id' => $this->kadiogo->id]);
        $reponse = $this->actingAs($dp)->get(route('dashboard', ['region_id' => $this->guiriko->id]));
        $this->assertSame(3, $this->totalDossiers($reponse));

        $dr = $this->utilisateur('DR', ['region_id' => $this->centre->id]);
        $reponse = $this->actingAs($dr)->get(route('dashboard', ['region_id' => $this->guiriko->id]));
        $this->assertSame(3, $this->totalDossiers($reponse));
    }

    public function test_un_compte_non_rattache_ne_voit_aucune_statistique(): void
    {
        $dr = $this->utilisateur('DR');

        $reponse = $this->actingAs($dr)->get(route('dashboard'))->assertOk()
            ->assertSee('n’est rattaché à aucune région', false);
        $this->assertSame(0, $this->totalDossiers($reponse));
        $this->assertSame(0, $reponse->viewData('signalements')->sum());
        $this->assertTrue($reponse->viewData('repartition')->isEmpty());
    }

    public function test_l_administrateur_releve_du_niveau_central(): void
    {
        $admin = $this->utilisateur('administrateur');

        $reponse = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Niveau central');
        $this->assertSame(6, $this->totalDossiers($reponse));
    }

    public function test_l_evolution_compte_les_integrations_du_mois(): void
    {
        $reponse = $this->actingAs($this->utilisateur('administrateur'))->get(route('dashboard'));
        $moisCourant = $reponse->viewData('evolution')->last();

        $this->assertSame(2, $moisCourant['integres']);
        $this->assertSame(3, $moisCourant['signalements']);
        $this->assertCount(6, $reponse->viewData('evolution'));
    }

    // ---------- Rattachement des comptes ----------

    public function test_un_dp_doit_etre_rattache_a_une_province_dont_la_region_est_deduite(): void
    {
        $this->actingAs($this->utilisateur('administrateur'));
        $donnees = ['name' => 'DP Kadiogo', 'email' => 'dp@oev.bf', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'DP'];

        $this->post(route('users.store'), $donnees)->assertSessionHasErrors('province_id');

        $this->post(route('users.store'), $donnees + ['province_id' => $this->kadiogo->id])->assertSessionHasNoErrors();
        $dp = User::where('email', 'dp@oev.bf')->firstOrFail();
        $this->assertSame($this->kadiogo->id, $dp->province_id);
        $this->assertSame($this->centre->id, $dp->region_id);
    }

    public function test_un_dr_doit_etre_rattache_a_une_region(): void
    {
        $this->actingAs($this->utilisateur('administrateur'));
        $donnees = ['name' => 'DR Centre', 'email' => 'dr@oev.bf', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'DR'];

        $this->post(route('users.store'), $donnees)->assertSessionHasErrors('region_id');

        // Une province transmise par erreur n'est pas conservée pour un DR
        $this->post(route('users.store'), $donnees + ['region_id' => $this->centre->id, 'province_id' => $this->houet->id])->assertSessionHasNoErrors();
        $dr = User::where('email', 'dr@oev.bf')->firstOrFail();
        $this->assertSame($this->centre->id, $dr->region_id);
        $this->assertNull($dr->province_id);
    }

    public function test_passer_au_niveau_central_retire_le_rattachement(): void
    {
        $this->actingAs($this->utilisateur('administrateur'));
        $dp = $this->utilisateur('DP', ['region_id' => $this->centre->id, 'province_id' => $this->kadiogo->id]);

        // Sans changement de rôle, le DP garde l'obligation d'avoir une province
        $this->put(route('users.update', $dp), ['name' => $dp->name, 'email' => $dp->email, 'active' => 1])->assertSessionHasErrors('province_id');

        $this->put(route('users.update', $dp), ['name' => $dp->name, 'email' => $dp->email, 'active' => 1, 'role' => 'agent DGFE'])->assertSessionHasNoErrors();
        $dp->refresh();
        $this->assertNull($dp->region_id);
        $this->assertNull($dp->province_id);
        $this->assertTrue($dp->hasRole('agent DGFE'));
    }

    public function test_la_gestion_des_utilisateurs_affiche_le_rattachement(): void
    {
        $this->utilisateur('DP', ['name' => 'Awa DP', 'region_id' => $this->centre->id, 'province_id' => $this->kadiogo->id]);

        $this->actingAs($this->utilisateur('administrateur'))->get(route('users.index'))->assertOk()
            ->assertSee('Kadiogo (Centre)')
            ->assertSee('Province du directeur provincial');
    }
}
