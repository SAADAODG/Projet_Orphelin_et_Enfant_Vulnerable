<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnTetesSecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_pages_publiques_et_admin_envoient_les_en_tetes_de_securite(): void
    {
        $reponses = [
            $this->get(route('public.home')),
            $this->get(route('login')),
            $this->actingAs(User::factory()->create())->get(route('dashboard')),
        ];

        foreach ($reponses as $reponse) {
            $reponse->assertOk()
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
                ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
                ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
                ->assertHeaderMissing('X-Powered-By')
                ->assertHeaderMissing('Strict-Transport-Security');

            $csp = $reponse->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("default-src 'self'", $csp);
            $this->assertStringContainsString("frame-ancestors 'self'", $csp);
            $this->assertStringContainsString("object-src 'none'", $csp);
        }
    }

    public function test_la_csp_est_reservee_aux_pages_html(): void
    {
        $this->getJson('/page-inexistante')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_hsts_uniquement_en_https(): void
    {
        $this->get(str_replace('http://', 'https://', route('login')))
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
