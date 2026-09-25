<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}