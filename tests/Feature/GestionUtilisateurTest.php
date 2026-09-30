<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionUtilisateurTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_la_liste_affiche_les_utilisateurs_de_la_base(): void
    {
        $administrateur = User::factory()->create(['name' => 'Administrateur OEV', 'email' => 'admin@oev.bf'])->assignRole('administrateur');

        $response = $this->actingAs($administrateur)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Administrateur OEV');
        $response->assertSee('admin@oev.bf');
        $response->assertSee('1 compte(s) trouvé(s).');
    }

    public function test_la_gestion_des_utilisateurs_est_reservee_aux_roles_autorises(): void
    {
        $dp = User::factory()->create()->assignRole('DP');

        $this->actingAs($dp)->get(route('users.index'))->assertAccesRefuse();
        $this->post(route('users.store'), ['name' => 'X', 'email' => 'x@oev.bf', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'DP'])->assertAccesRefuse();
        $this->assertDatabaseMissing('users', ['email' => 'x@oev.bf']);
    }

    public function test_le_responsable_dgfe_consulte_sans_pouvoir_modifier(): void
    {
        $responsable = User::factory()->create()->assignRole('responsable DGFE');
        $autre = User::factory()->create();

        $this->actingAs($responsable)->get(route('users.index'))->assertOk()
            ->assertDontSee('data-bs-target="#ajouterUtilisateurModal"', false)
            ->assertDontSee('data-bs-target="#modifierUtilisateur' . $autre->id . '"', false);
        $this->delete(route('users.destroy', $autre))->assertAccesRefuse();
        $this->assertNotSoftDeleted($autre);
    }
}
