<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Oev;
use App\Models\User;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OevTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Commune $ouagadougou;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);
        Storage::fake('local');
        $this->ouagadougou = Commune::where('nom', 'Ouagadougou')->firstOrFail();

        $this->agent = User::factory()->create();
        $this->agent->assignRole('agent DGFE');
    }

    private function donneesOev(array $surcharge = []): array
    {
        return $surcharge + [
            'nom' => 'OUEDRAOGO',
            'prenom' => 'Awa',
            'sexe' => 'F',
            'date_naissance' => now()->subYears(11)->subMonths(2)->toDateString(),
            'statut' => 'orphelin_pere',
            'handicap' => '0',
            'systeme_educatif' => 'classique',
            'nom_tuteur' => 'SAWADOGO',
            'prenom_tuteur' => 'Issa',
            'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_precedent' => 'École A de Koulouba',
            'moyenne_annuelle' => '14.5',
            'appreciation' => 'admis',
            'etablissement_actuel' => 'Lycée Philippe Zinda Kaboré',
            'type_etablissement' => 'public',
            'classe' => '6e',
            'frais_scolarite' => 25000,
            'region_id' => $this->ouagadougou->province->region_id,
            'province_id' => $this->ouagadougou->province_id,
            'commune_id' => $this->ouagadougou->id,
        ];
    }

    private function pieces(): array
    {
        return [
            'acte_naissance' => UploadedFile::fake()->create('acte.pdf', 100, 'application/pdf'),
            'certificat_scolarite' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
            'photo' => UploadedFile::fake()->image('photo.jpg'),
            'cnib_tuteur' => UploadedFile::fake()->create('cnib.pdf', 100, 'application/pdf'),
            'rib' => UploadedFile::fake()->create('rib.pdf', 100, 'application/pdf'),
        ];
    }

    public function test_un_agent_peut_enregistrer_un_oev_sans_pieces(): void
    {
        $this->actingAs($this->agent)->get(route('oevs.create'))->assertOk();
        $this->post(route('oevs.store'), $this->donneesOev())->assertSessionHasNoErrors()->assertRedirect();

        $oev = Oev::firstOrFail();
        $this->assertSame('OEV-' . now()->year . '-0001', $oev->code);
        $this->assertSame('aucun', $oev->etatDossier());
        $this->assertSame('OUEDRAOGO', $oev->nom);
        $this->assertSame('Awa', $oev->prenom);
        $this->assertSame('Issa', $oev->prenom_tuteur);
        $this->assertSame(11, $oev->age());
        $this->get(route('oevs.show', $oev))->assertOk()->assertSeeInOrder(['OUEDRAOGO', 'Awa', $oev->code]);
    }

    public function test_enregistrement_avec_toutes_les_pieces_donne_un_dossier_complet(): void
    {
        $this->actingAs($this->agent)
            ->post(route('oevs.store'), $this->donneesOev(['nom_structure_rib' => 'Association Espoir']) + $this->pieces())
            ->assertSessionHasNoErrors();

        $oev = Oev::firstOrFail();
        $this->assertSame('complet', $oev->etatDossier());
        foreach ($oev->documents as $document) {
            Storage::disk('local')->assertExists($document->chemin);
        }
        $this->get(route('oevs.documents.show', [$oev, $oev->documents->first()]))->assertOk();
    }

    public function test_le_rib_exige_le_nom_de_la_structure(): void
    {
        $this->actingAs($this->agent)
            ->post(route('oevs.store'), $this->donneesOev() + ['rib' => UploadedFile::fake()->create('rib.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('nom_structure_rib');
    }

    public function test_la_nature_du_handicap_est_exigee_si_handicap(): void
    {
        $this->actingAs($this->agent)
            ->post(route('oevs.store'), $this->donneesOev(['handicap' => '1']))
            ->assertSessionHasErrors('nature_handicap');
    }

    public function test_la_date_de_naissance_doit_etre_plausible(): void
    {
        $this->actingAs($this->agent);
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => now()->addDay()->toDateString()]))->assertSessionHasErrors('date_naissance');
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => '1980-05-12']))->assertSessionHasErrors('date_naissance');
        $this->post(route('oevs.store'), $this->donneesOev(['date_naissance' => 'pas une date']))->assertSessionHasErrors('date_naissance');
    }

    public function test_le_telephone_est_enregistre_avec_l_indicatif_du_burkina(): void
    {
        $this->actingAs($this->agent)->post(route('oevs.store'), $this->donneesOev(['contact_tuteur' => '70 12 34 56']))->assertSessionHasNoErrors();
        $this->post(route('oevs.store'), $this->donneesOev(['nom' => 'KABORE', 'contact_tuteur' => '+22676543210']))->assertSessionHasNoErrors();

        $this->assertSame('+226 70 12 34 56', Oev::where('nom', 'OUEDRAOGO')->value('contact_tuteur'));
        $this->assertSame('+226 76 54 32 10', Oev::where('nom', 'KABORE')->value('contact_tuteur'));
    }

    public function test_les_messages_d_erreur_du_formulaire_sont_en_francais(): void
    {
        $this->actingAs($this->agent)
            ->post(route('oevs.store'), $this->donneesOev(['classe' => '', 'region_id' => '']))
            ->assertSessionHasErrors([
                'classe' => 'Le champ « classe » est obligatoire.',
                'region_id' => 'Le champ « région » est obligatoire.',
            ]);
    }

    public function test_la_localite_est_rattachee_aux_tables_et_doit_etre_coherente(): void
    {
        $this->actingAs($this->agent)->post(route('oevs.store'), $this->donneesOev())->assertSessionHasNoErrors();
        $oev = Oev::firstOrFail();
        $this->assertSame('Ouagadougou', $oev->commune->nom);
        $this->assertSame('Kadiogo', $oev->province->nom);
        $this->get(route('oevs.show', $oev))->assertSee('Ouagadougou');

        // Une commune qui n'appartient pas à la province choisie est refusée.
        $autreCommune = Commune::where('province_id', '!=', $this->ouagadougou->province_id)->firstOrFail();
        $this->post(route('oevs.store'), $this->donneesOev(['nom' => 'KABORE', 'commune_id' => $autreCommune->id]))
            ->assertSessionHasErrors('commune_id');

        // Une commune utilisée par un dossier ne peut pas être supprimée.
        $admin = User::factory()->create();
        $admin->assignRole('administrateur');
        $this->actingAs($admin)->delete(route('localites.communes.destroy', $this->ouagadougou))->assertSessionHas('error');
        $this->assertModelExists($this->ouagadougou);
    }

    public function test_un_telephone_incomplet_est_refuse(): void
    {
        $this->actingAs($this->agent)
            ->post(route('oevs.store'), $this->donneesOev(['contact_tuteur' => '70 12 34']))
            ->assertSessionHasErrors('contact_tuteur');
    }

    public function test_on_peut_completer_le_dossier_plus_tard(): void
    {
        $this->actingAs($this->agent)->post(route('oevs.store'), $this->donneesOev());
        $oev = Oev::firstOrFail();

        $this->get(route('oevs.edit', $oev))->assertOk();
        $this->put(route('oevs.update', $oev), $this->donneesOev(['classe' => '5e', 'nom_structure_rib' => 'ONG Avenir']) + $this->pieces())
            ->assertSessionHasNoErrors();

        $oev->refresh();
        $this->assertSame('5e', $oev->classe);
        $this->assertSame('complet', $oev->etatDossier());
    }

    public function test_la_liste_filtre_les_oev_avec_et_sans_dossier(): void
    {
        $this->actingAs($this->agent)->post(route('oevs.store'), $this->donneesOev(['nom_structure_rib' => 'ONG Avenir']) + $this->pieces());
        $this->post(route('oevs.store'), $this->donneesOev(['nom' => 'KABORE', 'prenom' => 'Paul', 'sexe' => 'M']));

        $this->get(route('oevs.index'))->assertOk()->assertSee('OUEDRAOGO')->assertSee('KABORE');
        $this->get(route('oevs.index', ['dossier' => 'complet']))->assertSee('OUEDRAOGO')->assertDontSee('KABORE');
        $this->get(route('oevs.index', ['dossier' => 'aucun']))->assertSee('KABORE')->assertDontSee('OUEDRAOGO');
    }

    public function test_la_liste_affiche_code_nom_prenom_statut_et_la_fiche_le_detail(): void
    {
        $this->actingAs($this->agent)->post(route('oevs.store'), $this->donneesOev());
        $oev = Oev::firstOrFail();

        $this->get(route('oevs.index'))
            ->assertSeeInOrder([$oev->code, 'OUEDRAOGO', 'Awa', 'Orphelin de père'])
            ->assertSee(route('oevs.show', $oev))
            ->assertDontSee('SAWADOGO')        // le tuteur n'apparaît plus dans la liste
            ->assertDontSee('Lycée Philippe'); // ni la scolarité

        $this->get(route('oevs.show', $oev))
            ->assertSee('SAWADOGO')->assertSee('+226 70 00 00 00')->assertSee('Lycée Philippe Zinda Kaboré')->assertSee('Kadiogo');
    }

    public function test_un_dp_peut_voir_mais_pas_enregistrer(): void
    {
        $dp = User::factory()->create();
        $dp->assignRole('DP');

        $this->actingAs($dp)->get(route('oevs.index'))->assertOk();
        $this->get(route('oevs.create'))->assertForbidden();
        $this->post(route('oevs.store'), $this->donneesOev())->assertForbidden();
    }
}
