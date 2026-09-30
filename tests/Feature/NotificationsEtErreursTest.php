<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Province;
use App\Models\Region;
use App\Models\Signalement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Notifications SweetAlert (partials/alertes, assets/js/alertes.js) et gestion des erreurs sans page brute
 * (App\Exceptions\RetourApresErreur).
 */
class NotificationsEtErreursTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('administrateur');

        // Routes de test simulant des erreurs imprévues
        Route::middleware('web')->group(function () {
            Route::get('/test/erreur-page', fn () => throw new RuntimeException('panne'));
            Route::post('/test/erreur-formulaire', fn () => throw new RuntimeException('panne'));
            Route::post('/test/session-expiree', fn () => throw new TokenMismatchException());
        });
    }

    public function test_les_pages_chargent_sweetalert_et_transmettent_les_messages(): void
    {
        $this->actingAs($this->admin)->withSession(['success' => 'Enregistré.', 'error' => 'Échec.'])
            ->get(route('dashboard'))->assertOk()
            ->assertSee('assets/vendors/sweetalert2/sweetalert2.all.min.js', false)
            ->assertSee('assets/js/alertes.js', false)
            // L'erreur est affichée avant le succès
            ->assertSeeInOrder(['"type":"error"', '"type":"success"'], false);
    }

    public function test_aucune_boite_de_confirmation_native_du_navigateur(): void
    {
        $vues = collect(\Illuminate\Support\Facades\File::allFiles(resource_path('views')))
            ->filter(fn ($fichier) => str_contains($fichier->getContents(), 'confirm('))
            ->map->getRelativePathname();

        $this->assertEmpty($vues->all(), 'confirm() natif encore utilisé dans : ' . $vues->implode(', '));
    }

    public function test_un_acces_refuse_ramene_a_la_page_precedente(): void
    {
        $dp = User::factory()->create()->assignRole('DP');

        $this->actingAs($dp)->from(route('dashboard'))->get(route('users.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning', 'Vous n’avez pas accès à cette page ou à cette action.');
    }

    public function test_un_acces_direct_refuse_ramene_au_tableau_de_bord(): void
    {
        $dp = User::factory()->create()->assignRole('DP');

        $this->actingAs($dp)->get(route('users.index'))->assertRedirect(route('dashboard'));
    }

    public function test_le_message_d_une_action_impossible_est_transmis(): void
    {
        $region = Region::create(['nom' => 'Centre']);
        $province = Province::create(['region_id' => $region->id, 'nom' => 'Kadiogo']);
        $commune = Commune::create(['province_id' => $province->id, 'nom' => 'Ouagadougou']);
        $signalement = Signalement::forceCreate([
            'recepisse' => Signalement::genererRecepisse(), 'enfant_nom' => 'KABORE', 'enfant_prenom' => 'Awa', 'enfant_age' => 9,
            'vulnerabilite' => 'orphelin', 'region_id' => $region->id, 'province_id' => $province->id, 'commune_id' => $commune->id,
            'localite' => 'Secteur 1', 'declarant_nom' => 'D', 'declarant_prenom' => 'A', 'declarant_telephone' => '70000000',
            'declarant_adresse' => 'Secteur 1', 'declarant_profession' => 'Commerçante', 'declarant_lien' => 'voisin',
            'statut' => Signalement::REJETE,
        ]);

        $this->actingAs($this->admin)->from(route('admin.signalements.show', $signalement))
            ->patch(route('admin.signalements.valider', $signalement))
            ->assertRedirect(route('admin.signalements.show', $signalement))
            ->assertSessionHas('warning', 'Ce signalement a déjà été traité.');
    }

    public function test_une_session_expiree_ramene_au_formulaire_avec_la_saisie(): void
    {
        $this->from('/login')->post('/test/session-expiree', ['email' => 'agent@oev.bf', 'password' => 'secret'])
            ->assertRedirect('/login')
            ->assertSessionHas('warning', 'Votre session a expiré. Veuillez recommencer l’opération.')
            ->assertSessionHasInput('email', 'agent@oev.bf')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_une_erreur_serveur_apres_un_formulaire_ramene_au_formulaire_en_production(): void
    {
        config(['app.debug' => false]);

        $this->actingAs($this->admin)->from(route('dashboard'))->post('/test/erreur-formulaire', ['nom' => 'KABORE'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error')
            ->assertSessionHasInput('nom', 'KABORE');
    }

    public function test_une_erreur_serveur_sur_une_page_affiche_la_page_d_erreur_sans_boucle(): void
    {
        config(['app.debug' => false]);

        $this->actingAs($this->admin)->get('/test/erreur-page')
            ->assertStatus(500)
            ->assertSee('Un problème est survenu');
    }
}
