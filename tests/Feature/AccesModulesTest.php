<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Oev;
use App\Models\Plainte;
use App\Models\Province;
use App\Models\Region;
use App\Models\Signalement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Chaque acteur ne voit que les modules de son rôle, et dans ces modules que les données de sa zone
 * et de son étape : signalements et constitution chez le DP, validation chez le DR, dossiers validés
 * au niveau central.
 */
class AccesModulesTest extends TestCase
{
    use RefreshDatabase;

    private Commune $ouagadougou;
    private Commune $kombissiri;
    private Commune $boboDioulasso;
    private User $dpKadiogo;
    private User $drCentre;
    private User $central;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $centre = Region::create(['nom' => 'Centre']);
        $guiriko = Region::create(['nom' => 'Guiriko']);
        $kadiogo = Province::create(['region_id' => $centre->id, 'nom' => 'Kadiogo']);
        $bazega = Province::create(['region_id' => $centre->id, 'nom' => 'Bazèga']);
        $houet = Province::create(['region_id' => $guiriko->id, 'nom' => 'Houet']);
        $this->ouagadougou = Commune::create(['province_id' => $kadiogo->id, 'nom' => 'Ouagadougou']);
        $this->kombissiri = Commune::create(['province_id' => $bazega->id, 'nom' => 'Kombissiri']);
        $this->boboDioulasso = Commune::create(['province_id' => $houet->id, 'nom' => 'Bobo-Dioulasso']);

        $this->dpKadiogo = User::factory()->create(['region_id' => $centre->id, 'province_id' => $kadiogo->id])->assignRole('DP');
        $this->drCentre = User::factory()->create(['region_id' => $centre->id])->assignRole('DR');
        $this->central = User::factory()->create()->assignRole('agent DGFE');
    }

    private function dossier(Commune $commune, string $etat, array $attributs = []): Oev
    {
        return Oev::create($attributs + [
            'nom' => 'ENFANT', 'prenom' => Str::random(6), 'sexe' => 'F', 'date_naissance' => '2015-03-01',
            'statut' => 'orphelin_pere', 'handicap' => false, 'systeme_educatif' => 'classique',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_actuel' => 'École A', 'type_etablissement' => 'public', 'classe' => 'CM1', 'frais_scolarite' => 0,
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'numero_dossier' => Oev::genererNumeroDossier(),
            'statut_dossier' => $etat,
            'code' => $etat === Oev::ETAT_INTEGRE ? Oev::genererCode() : null,
            'integre_at' => $etat === Oev::ETAT_INTEGRE ? now() : null,
        ]);
    }

    private function signalement(Commune $commune, string $nom): Signalement
    {
        return Signalement::forceCreate([
            'recepisse' => Signalement::genererRecepisse(),
            'enfant_nom' => $nom, 'enfant_prenom' => 'Test', 'enfant_age' => 9, 'vulnerabilite' => 'orphelin',
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'localite' => 'Secteur 1',
            'declarant_nom' => 'DECLARANT', 'declarant_prenom' => 'Awa', 'declarant_telephone' => '70000000',
            'declarant_adresse' => 'Secteur 1', 'declarant_profession' => 'Commerçante', 'declarant_lien' => 'voisin',
            'statut' => Signalement::EN_ATTENTE,
        ]);
    }

    // ---------- Menu ----------

    public function test_le_menu_du_dp_ne_montre_que_ses_modules(): void
    {
        $this->actingAs($this->dpKadiogo)->get(route('dashboard'))->assertOk()
            ->assertSee(route('admin.signalements.index'))
            ->assertSee(route('oevs.index'))
            ->assertSee(route('oevs.liste'))
            ->assertDontSee(route('oevs.validation'))
            ->assertDontSee(route('oevs.integration'))
            ->assertDontSee(route('admin.plaintes.index'))
            ->assertDontSee(route('users.index'))
            ->assertDontSee(route('roles-permissions.index'))
            ->assertDontSee(route('parametres.edit'))
            ->assertDontSee('Gestion des demandes');
    }

    public function test_le_menu_du_dr_et_du_central(): void
    {
        $this->actingAs($this->drCentre)->get(route('dashboard'))->assertOk()
            ->assertSee(route('admin.signalements.index'))
            ->assertSee(route('oevs.validation'))
            ->assertDontSee(route('oevs.create'))
            ->assertDontSee(route('oevs.integration'))
            ->assertDontSee(route('admin.plaintes.index'));

        $this->actingAs($this->central)->get(route('dashboard'))->assertOk()
            ->assertSee(route('oevs.integration'))
            ->assertSee(route('admin.plaintes.index'))
            ->assertDontSee(route('admin.signalements.index'))
            ->assertDontSee(route('oevs.validation'))
            ->assertDontSee(route('users.index'));
    }

    public function test_l_administrateur_voit_tous_les_modules(): void
    {
        $admin = User::factory()->create()->assignRole('administrateur');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee(route('admin.signalements.index'))
            ->assertSee(route('oevs.integration'))
            ->assertSee(route('admin.plaintes.index'))
            ->assertSee(route('users.index'))
            ->assertSee(route('roles-permissions.index'));
    }

    // ---------- Signalements ----------

    public function test_le_dp_ne_voit_et_ne_traite_que_les_signalements_de_sa_province(): void
    {
        $chezLui = $this->signalement($this->ouagadougou, 'KABORE');
        $ailleurs = $this->signalement($this->kombissiri, 'SAWADOGO');

        $this->actingAs($this->dpKadiogo)->get(route('admin.signalements.index'))->assertOk()
            ->assertSee('KABORE')->assertDontSee('SAWADOGO');
        $this->get(route('admin.signalements.show', $ailleurs))->assertAccesRefuse();
        $this->patch(route('admin.signalements.valider', $ailleurs))->assertAccesRefuse();

        $this->patch(route('admin.signalements.valider', $chezLui))->assertRedirect();
        $this->assertSame(Signalement::VALIDE, $chezLui->refresh()->statut);
    }

    public function test_le_dr_consulte_les_signalements_de_sa_region_sans_les_traiter(): void
    {
        $kadiogo = $this->signalement($this->ouagadougou, 'KABORE');
        $this->signalement($this->kombissiri, 'SAWADOGO');
        $this->signalement($this->boboDioulasso, 'TRAORE');

        $this->actingAs($this->drCentre)->get(route('admin.signalements.index'))->assertOk()
            ->assertSee('KABORE')->assertSee('SAWADOGO')->assertDontSee('TRAORE');
        $this->get(route('admin.signalements.show', $kadiogo))->assertOk()
            ->assertDontSee(route('admin.signalements.valider', $kadiogo))
            ->assertSee('En attente d’examen par la direction provinciale', false);
        $this->patch(route('admin.signalements.valider', $kadiogo))->assertAccesRefuse();
        $this->assertSame(Signalement::EN_ATTENTE, $kadiogo->refresh()->statut);
    }

    public function test_le_niveau_central_n_a_pas_acces_aux_signalements(): void
    {
        $signalement = $this->signalement($this->ouagadougou, 'KABORE');

        $this->actingAs($this->central);
        $this->get(route('admin.signalements.index'))->assertAccesRefuse();
        $this->get(route('admin.signalements.show', $signalement))->assertAccesRefuse();
    }

    // ---------- Dossiers enfants ----------

    public function test_le_central_ne_voit_un_dossier_qu_apres_validation_du_dr(): void
    {
        $soumis = $this->dossier($this->ouagadougou, Oev::ETAT_SOUMIS);
        $valide = $this->dossier($this->ouagadougou, Oev::ETAT_VALIDE);

        $this->actingAs($this->central);
        $this->get(route('oevs.show', $soumis))->assertAccesRefuse();
        $this->get(route('oevs.show', $valide))->assertOk();
        $this->get(route('oevs.integration', ['etat' => Oev::ETAT_VALIDE]))->assertSee($valide->numero_dossier)->assertDontSee($soumis->numero_dossier);
    }

    public function test_le_central_ne_voit_que_les_dossiers_qu_il_a_lui_meme_rejetes(): void
    {
        $rejetDr = $this->dossier($this->ouagadougou, Oev::ETAT_REJETE, ['rejete_niveau' => Oev::REJET_DR, 'motif_rejet' => 'Hors critères']);
        $rejetCentral = $this->dossier($this->ouagadougou, Oev::ETAT_REJETE, ['rejete_niveau' => Oev::REJET_CENTRAL, 'motif_rejet' => 'Doublon']);

        $this->actingAs($this->central);
        $this->get(route('oevs.integration', ['etat' => Oev::ETAT_REJETE]))->assertOk()
            ->assertSee($rejetCentral->numero_dossier)
            ->assertDontSee($rejetDr->numero_dossier);
        $this->get(route('oevs.show', $rejetDr))->assertAccesRefuse();
        $this->get(route('oevs.show', $rejetCentral))->assertOk();

        // Le DR, lui, voit les deux rejets de sa région
        $this->actingAs($this->drCentre)->get(route('oevs.validation', ['etat' => Oev::ETAT_REJETE]))
            ->assertSee($rejetDr->numero_dossier)
            ->assertSee($rejetCentral->numero_dossier);
    }

    public function test_le_dp_et_le_dr_ne_voient_que_les_dossiers_de_leur_zone(): void
    {
        $kadiogo = $this->dossier($this->ouagadougou, Oev::ETAT_SOUMIS);
        $bazega = $this->dossier($this->kombissiri, Oev::ETAT_SOUMIS);
        $houet = $this->dossier($this->boboDioulasso, Oev::ETAT_SOUMIS);

        $this->actingAs($this->dpKadiogo);
        $this->get(route('oevs.index'))->assertSee($kadiogo->numero_dossier)->assertDontSee($bazega->numero_dossier);
        $this->get(route('oevs.show', $bazega))->assertAccesRefuse();

        $this->actingAs($this->drCentre);
        $this->get(route('oevs.validation'))->assertSee($kadiogo->numero_dossier)->assertSee($bazega->numero_dossier)->assertDontSee($houet->numero_dossier);
        $this->get(route('oevs.show', $houet))->assertAccesRefuse();
        $this->post(route('oevs.conforme', $houet))->assertAccesRefuse();
        $this->assertSame(Oev::ETAT_SOUMIS, $houet->refresh()->statut_dossier);
    }

    public function test_le_dp_ne_constitue_des_dossiers_que_dans_sa_province(): void
    {
        $this->actingAs($this->dpKadiogo)->get(route('oevs.create'))->assertOk()
            ->assertSee('data-valeur="' . $this->dpKadiogo->province_id . '"', false);

        $this->post(route('oevs.store'), [
            'nom' => 'OUEDRAOGO', 'prenom' => 'Awa', 'sexe' => 'F', 'date_naissance' => now()->subYears(10)->toDateString(),
            'statut' => 'orphelin_pere', 'handicap' => '0', 'systeme_educatif' => 'classique',
            'nom_tuteur' => 'SAWADOGO', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '70 00 00 00',
            'etablissement_actuel' => 'École A', 'type_etablissement' => 'public', 'classe' => 'CM1', 'frais_scolarite' => 0,
            'region_id' => $this->boboDioulasso->province->region_id, 'province_id' => $this->boboDioulasso->province_id, 'commune_id' => $this->boboDioulasso->id,
        ])->assertSessionHasErrors(['province_id' => 'Vous ne pouvez enregistrer que des dossiers de votre province (Kadiogo).']);
        $this->assertSame(0, Oev::count());
    }

    // ---------- Plaintes ----------

    public function test_les_plaintes_relevent_du_niveau_central(): void
    {
        $plainte = Plainte::forceCreate(['reference' => Plainte::genererReference(), 'objet' => 'mecontentement', 'description' => 'Retard de paiement', 'statut' => Plainte::NOUVELLE]);

        $this->actingAs($this->dpKadiogo)->get(route('admin.plaintes.index'))->assertAccesRefuse();
        $this->actingAs($this->drCentre)->get(route('admin.plaintes.show', $plainte))->assertAccesRefuse();

        $this->actingAs($this->central)->get(route('admin.plaintes.index'))->assertOk()->assertSee($plainte->reference);
        $this->patch(route('admin.plaintes.statut', $plainte), ['statut' => Plainte::EN_COURS])->assertRedirect();
        $this->assertSame(Plainte::EN_COURS, $plainte->refresh()->statut);
    }
}
