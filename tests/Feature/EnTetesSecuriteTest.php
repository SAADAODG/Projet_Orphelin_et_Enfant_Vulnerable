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
            $reponse->assertHeaderMissing('Content-Security-Policy-Report-Only');
        }
    }

    public function test_seuls_les_scripts_portant_le_nonce_de_la_page_sont_autorises(): void
    {
        $premiere = $this->get(route('login'));
        $seconde = $this->get(route('login'));

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)';/", $premiere->headers->get('Content-Security-Policy'));
        preg_match("/'nonce-([^']+)'/", $premiere->headers->get('Content-Security-Policy'), $nonce);
        preg_match("/'nonce-([^']+)'/", $seconde->headers->get('Content-Security-Policy'), $autreNonce);

        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $premiere->headers->get('Content-Security-Policy'));
        $this->assertNotSame($nonce[1], $autreNonce[1], 'Le nonce doit changer à chaque requête.');
        // Le script écrit dans la page porte le nonce annoncé par l'en-tête
        $premiere->assertSee('<script nonce="' . $nonce[1] . '">', false);
    }

    public function test_aucune_vue_ne_contient_de_script_que_la_csp_bloquerait(): void
    {
        $fautifs = [];
        $vues = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($vues as $vue) {
            if (! str_ends_with($vue->getFilename(), '.blade.php')) {
                continue;
            }
            $contenu = file_get_contents($vue->getPathname());
            // <script> sans nonce ni src, attribut onclick/onchange…, lien javascript:
            if (preg_match('/<script(?![^>]*\b(?:nonce|src|type="application\/json")=?)[^>]*>|\son[a-z]+\s*=\s*["\']|javascript:/i', $contenu, $m)) {
                $fautifs[] = str_replace(resource_path('views'), '', $vue->getPathname()) . ' : ' . trim($m[0]);
            }
        }

        $this->assertSame([], $fautifs, 'Scripts bloqués par la CSP (ajouter nonce="{{ $cspNonce }}" ou passer par addEventListener).');
    }

    public function test_mode_observation_garde_l_ancienne_regle_et_observe_la_nouvelle(): void
    {
        config(['app.csp_scripts_observation' => true]);

        $reponse = $this->get(route('login'))->assertOk();

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $reponse->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'self' 'nonce-", $reponse->headers->get('Content-Security-Policy-Report-Only'));
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
