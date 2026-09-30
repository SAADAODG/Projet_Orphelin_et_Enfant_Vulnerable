<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_be_redirected_to_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@oev.bf',
            'password' => bcrypt('admin1234'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@oev.bf',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_disabled_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'desactive@oev.gov.bf',
            'password' => bcrypt('admin1234'),
            'active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'desactive@oev.gov.bf',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_un_mauvais_mot_de_passe_n_ouvre_pas_la_modale_mot_de_passe_oublie(): void
    {
        User::factory()->create(['email' => 'agent@oev.bf', 'password' => bcrypt('bon-mot-de-passe')]);

        $this->from('/login')->post('/login', ['email' => 'agent@oev.bf', 'password' => 'mauvais'])
            ->assertRedirect('/login');

        $this->get('/login')->assertOk()
            ->assertSee('Les informations de connexion sont incorrectes.')
            ->assertSee('var modalId = null;', false);
        $this->assertGuest();
    }

    public function test_la_page_de_connexion_propose_l_icone_oeil(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('data-afficher-mdp="password"', false)
            ->assertSee('bi bi-eye', false);
    }

    public function test_mot_de_passe_oublie_envoie_le_lien_et_l_annonce(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'agent@oev.bf']);

        $this->from('/login')->post(route('password.email'), ['email' => 'agent@oev.bf'])->assertRedirect('/login');
        Notification::assertSentTo($user, ResetPassword::class);

        $this->get('/login')->assertSee('un lien de réinitialisation vient de lui être envoyé', false);
    }

    public function test_mot_de_passe_oublie_ne_revele_pas_les_adresses_inconnues(): void
    {
        Notification::fake();

        $this->from('/login')->post(route('password.email'), ['email' => 'inconnu@oev.bf'])
            ->assertRedirect('/login')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        Notification::assertNothingSent();
    }

    public function test_une_adresse_invalide_rouvre_la_modale_avec_son_erreur(): void
    {
        // Vérifié sur la page affichée : la session est sérialisée en JSON (config/session.php)
        $this->from('/login')->post(route('password.email'), ['email' => 'pas-un-email'])->assertRedirect('/login');

        $this->get('/login')->assertSee('Saisissez une adresse e-mail valide.')
            ->assertSee('var modalId = "motDePasseOublieModal";', false);
    }

    public function test_reinitialisation_du_mot_de_passe(): void
    {
        $user = User::factory()->create(['email' => 'agent@oev.bf', 'password' => bcrypt('ancien-mdp')]);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'agent@oev.bf']))->assertOk()
            ->assertSee('var modalId = "nouveauMotDePasseModal";', false)
            ->assertSee('data-afficher-mdp="reset-password"', false);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'agent@oev.bf', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('nouveau-mdp', $user->refresh()->password));
        $this->post('/login', ['email' => 'agent@oev.bf', 'password' => 'nouveau-mdp'])->assertRedirect('/admin/dashboard');
    }

    public function test_un_lien_de_reinitialisation_invalide_est_explique(): void
    {
        User::factory()->create(['email' => 'agent@oev.bf']);

        $this->post(route('password.update'), [
            'token' => 'jeton-invalide', 'email' => 'agent@oev.bf', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp',
        ])->assertRedirect(route('password.reset', ['token' => 'jeton-invalide', 'email' => 'agent@oev.bf']));

        $this->get(route('password.reset', ['token' => 'jeton-invalide', 'email' => 'agent@oev.bf']))
            ->assertSee('Ce lien de réinitialisation est invalide ou a expiré.', false);
    }
}
