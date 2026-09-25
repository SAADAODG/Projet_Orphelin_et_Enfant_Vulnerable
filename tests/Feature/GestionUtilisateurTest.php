<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionUtilisateurTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_affiche_les_utilisateurs_de_la_base(): void
    {
        $administrateur = User::factory()->create(['name' => 'Administrateur OEV', 'email' => 'admin@oev.bf']);

        $response = $this->actingAs($administrateur)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Administrateur OEV');
        $response->assertSee('admin@oev.bf');
        $response->assertSee('1 compte(s) trouvé(s).');
    }
}