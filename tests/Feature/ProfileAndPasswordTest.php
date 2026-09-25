<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class ProfileAndPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_uses_and_updates_the_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Ancien nom', 'email' => 'ancien@oev.gov.bf']);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Nouveau nom',
            'email' => 'nouveau@oev.gov.bf',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nouveau nom', 'email' => 'nouveau@oev.gov.bf']);
        $this->actingAs($user)->get(route('profile'))->assertSee('Nouveau nom')->assertSee('nouveau@oev.gov.bf');
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'admin@oev.gov.bf']);

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_registration_route_is_removed(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_user_can_change_their_password_from_settings(): void
    {
        $user = User::factory()->create(['password' => Hash::make('ancien1234')]);

        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'ancien1234',
            'password' => 'nouveau1234',
            'password_confirmation' => 'nouveau1234',
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('nouveau1234', $user->fresh()->password));
    }

    public function test_login_page_only_exposes_password_recovery(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('password.request'));
        $response->assertDontSee('Créer un compte');
    }
}