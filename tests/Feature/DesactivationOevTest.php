<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class DesactivationOevTest extends TestCase
{
    use RefreshDatabase;

    private User $dp;
    private User $dr;
    private User $central;
    private Oev $oev;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);

        $commune = Commune::where('nom', 'Ouagadougou')->firstOrFail();
        $province = $commune->province;
        $this->dp = User::factory()->create(['region_id' => $province->region_id, 'province_id' => $province->id])->assignRole('DP');
        $this->dr = User::factory()->create(['region_id' => $province->region_id])->assignRole('DR');
        $this->central = User::factory()->create()->assignRole('agent DGFE');

        $this->oev = Oev::create([
            'nom' => 'OUEDRAOGO', 'prenom' => 'Awa', 'sexe' => 'F', 'date_naissance' => '2015-03-01',
            'statut' => 'orphelin_pere', 'handicap' => false, 'systeme_educatif' => 'classique',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_actuel' => 'École A', 'type_etablissement' => 'public', 'classe' => 'CM1', 'frais_scolarite' => 0,
            'region_id' => $province->region_id, 'province_id' => $province->id, 'commune_id' => $commune->id,
            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
            'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
        ]);
    }

    public function test_le_dp_demande_et_le_central_valide_la_desactivation(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.desactiver', $this->oev), ['desactivation_motif' => 'deces', 'desactivation_commentaire' => 'Décédé le 02/10/2026'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Oev::DESACTIVATION_DEMANDEE, $this->oev->refresh()->desactivation_etat);
        // Tant que la demande n'est pas validée, l'OEV reste bénéficiaire
        $this->assertSame(1, Oev::beneficiaires()->count());

        // Le DP ne décide pas lui-même
        $this->actingAs($this->dp)->post(route('oevs.desactivation.decider', $this->oev), ['decision' => 'valider'])->assertRedirect();
        $this->assertSame(Oev::DESACTIVATION_DEMANDEE, $this->oev->refresh()->desactivation_etat);

        $this->actingAs($this->central)->post(route('oevs.desactivation.decider', $this->oev), ['decision' => 'valider'])
            ->assertSessionHasNoErrors();
        $this->oev->refresh();
        $this->assertTrue($this->oev->estDesactive());
        $this->assertSame($this->central->id, $this->oev->desactive_par);
        $this->assertSame(0, Oev::beneficiaires()->count());

        $this->assertSame(
            ['oev.desactivation_demandee', 'oev.desactivation'],
            JournalActivite::de($this->oev)->get()->reverse()->pluck('action')->values()->all(),
        );
        $this->actingAs($this->central)->get(route('oevs.show', $this->oev))->assertOk()->assertSee('OEV désactivé')->assertSee('Décès');
    }

    public function test_le_central_refuse_une_demande_avec_un_motif(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.desactiver', $this->oev), ['desactivation_motif' => 'majorite']);

        $this->actingAs($this->central)->post(route('oevs.desactivation.decider', $this->oev), ['decision' => 'refuser'])
            ->assertSessionHasErrors('motif_refus');
        $this->actingAs($this->central)->post(route('oevs.desactivation.decider', $this->oev), ['decision' => 'refuser', 'motif_refus' => 'L’enfant a 16 ans'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->oev->refresh()->desactivation_etat);
        $this->assertSame('L’enfant a 16 ans', JournalActivite::de($this->oev)->first()->details['motif']);
    }

    public function test_le_motif_autre_exige_une_precision_et_le_dr_ne_desactive_pas(): void
    {
        $this->actingAs($this->dp)->post(route('oevs.desactiver', $this->oev), ['desactivation_motif' => 'autre'])
            ->assertSessionHasErrors('desactivation_commentaire');
        $this->actingAs($this->dr)->post(route('oevs.desactiver', $this->oev), ['desactivation_motif' => 'deces'])->assertRedirect();

        $this->assertNull($this->oev->refresh()->desactivation_etat);
    }

    public function test_un_oev_desactive_ne_beneficie_plus_d_aucune_aide_puis_est_reactive(): void
    {
        $this->actingAs($this->central)->post(route('oevs.desactiver', $this->oev), ['desactivation_motif' => 'deces'])->assertSessionHasNoErrors();
        $this->assertTrue($this->oev->refresh()->estDesactive());

        // Plus d'appui possible, plus compté parmi les éligibles
        $parrain = Parrain::create(['type' => 'ong', 'nom' => 'ONG Espoir']);
        $appui = [
            'oev_id' => $this->oev->id, 'parrain_id' => $parrain->id, 'annee' => '2026-2027',
            'nature_appui_id' => NatureAppui::where('code', 'sante')->value('id'),
        ];
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $appui)->assertSessionHasErrors('oev_id');
        $session = new SessionParrainage(['enveloppe' => 1000]);
        $this->assertSame([], $this->app->make(SourceSelection::class)->eligiblesParRegion($session));

        // Réactivation : niveau central, motif obligatoire
        $this->actingAs($this->dp)->post(route('oevs.reactiver', $this->oev), ['motif_reactivation' => 'Erreur'])->assertRedirect();
        $this->assertTrue($this->oev->refresh()->estDesactive());
        $this->actingAs($this->central)->post(route('oevs.reactiver', $this->oev), [])->assertSessionHasErrors('motif_reactivation');
        $this->actingAs($this->central)->post(route('oevs.reactiver', $this->oev), ['motif_reactivation' => 'Homonyme : décès d’un autre enfant'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->oev->refresh()->desactivation_etat);
        $this->actingAs($this->dp)->post(route('parrainage.appuis.store'), $appui)->assertSessionHasNoErrors();
    }
}
