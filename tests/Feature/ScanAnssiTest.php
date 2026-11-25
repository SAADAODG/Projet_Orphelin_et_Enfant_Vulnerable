<?php

namespace Tests\Feature;

use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\RefuserRequeteTronquee;
use App\Support\HotesAutorises;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

/**
 * Rapport de scan de l'ANSSI du 17/11/2026 (oev-05, ANO ajourné) : Vul-001 variables d'entrée
 * PHP, Vul-002 en-tête Host, Vul-003 redirection en http://, Vul-004 HSTS en double, Vul-005 CSP
 * permissive. Plus les en-têtes en double reprochés à sociale-03 (même hébergement).
 */
class ScanAnssiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Liste statique de Symfony : un test précédent a pu la remplir.
        Request::setTrustedHosts([]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    // ── Vul-001 : max_input_vars ───────────────────────────────────────────

    public function test_vul001_une_requete_tronquee_par_max_input_vars_est_refusee(): void
    {
        $this->app->bind(RefuserRequeteTronquee::class, fn () => new class extends RefuserRequeteTronquee
        {
            protected function requeteTronquee(): bool
            {
                return true;
            }
        });
        config(['app.debug' => false]);

        $reponse = $this->post(route('login'), ['email' => 'a@b.c', 'password' => 'x']);

        $reponse->assertStatus(400);
        $this->assertStringNotContainsString('Input variables exceeded', $reponse->getContent());
    }

    public function test_vul001_une_requete_normale_passe(): void
    {
        error_clear_last();

        $this->get(route('login'))->assertOk();
    }

    public function test_vul001_les_erreurs_php_ne_sont_jamais_affichees(): void
    {
        $userIni = File::get(public_path('.user.ini'));
        $htaccess = File::get(public_path('.htaccess'));

        foreach (['display_errors', 'display_startup_errors', 'html_errors'] as $reglage) {
            $this->assertMatchesRegularExpression("/^{$reglage} = Off$/m", $userIni, ".user.ini : {$reglage}");
            $this->assertMatchesRegularExpression("/php_flag {$reglage} Off/", $htaccess, ".htaccess : {$reglage}");
        }
    }

    // ── Vul-002 : en-tête Host ─────────────────────────────────────────────

    public function test_vul002_un_hote_forge_n_apparait_dans_aucun_lien(): void
    {
        $page = $this->withHeader('X-Forwarded-Host', 'attaquant.example')->get(route('login'))->assertOk();

        $this->assertStringNotContainsString('attaquant.example', $page->getContent());
    }

    public function test_vul002_x_forwarded_host_n_est_pas_cru_meme_du_proxy(): void
    {
        $bootstrap = File::get(base_path('bootstrap/app.php'));

        $this->assertStringNotContainsString('HEADER_X_FORWARDED_HOST', $bootstrap);
        $this->assertStringContainsString('HEADER_X_FORWARDED_PROTO', $bootstrap);
    }

    public function test_vul002_les_hotes_acceptes_sont_celui_d_app_url_et_trusted_hosts_exactement(): void
    {
        config(['app.url' => 'https://oev-05.mfs-test.gov.bf', 'app.trusted_hosts' => ' 10.46.80.55, ,OEV-05.mfs-test.gov.bf']);

        $this->assertSame(['^oev\-05\.mfs\-test\.gov\.bf$', '^10\.46\.80\.55$'], HotesAutorises::motifs());

        Request::setTrustedHosts(HotesAutorises::motifs());
        $this->assertSame('oev-05.mfs-test.gov.bf', Request::create('https://oev-05.mfs-test.gov.bf/')->getHost());

        foreach (['attaquant.example', 'x.oev-05.mfs-test.gov.bf', 'oev-05.mfs-test.gov.bf.attaquant.example'] as $forge) {
            try {
                Request::create("https://{$forge}/")->getHost();
                $this->fail("Hôte forgé accepté : {$forge}");
            } catch (SuspiciousOperationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_vul002_hors_tests_une_requete_sous_un_hote_forge_recoit_une_400(): void
    {
        // Laravel désactive TrustHosts pendant les tests : on le force, comme en production.
        $this->app->bind(TrustHosts::class, fn ($app) => new class($app) extends TrustHosts
        {
            protected function shouldSpecifyTrustedHosts()
            {
                return true;
            }
        });
        config(['app.url' => 'https://oev-05.mfs-test.gov.bf', 'app.trusted_hosts' => '']);

        $this->get('https://oev-05.mfs-test.gov.bf/up')->assertOk();
        $this->get('https://attaquant.example/up')->assertStatus(400);
        $this->get('https://attaquant.example/login')->assertStatus(400);
    }

    // ── Vul-003 : aucune redirection en http:// ────────────────────────────

    public function test_vul003_le_htaccess_ne_redirige_plus_les_barres_obliques_finales(): void
    {
        $htaccess = File::get(public_path('.htaccess'));

        $this->assertDoesNotMatchRegularExpression('/^\s*RewriteRule[^\n]*R=30[12]/m', $htaccess);
    }

    public function test_vul003_une_adresse_avec_barre_oblique_finale_est_servie_sans_redirection(): void
    {
        $this->get('/login/')->assertOk();
    }

    // ── Vul-004 : HSTS et en-têtes en un seul endroit ──────────────────────

    public function test_vul004_l_application_ne_pose_jamais_hsts(): void
    {
        $this->get(str_replace('http://', 'https://', route('login')))
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_derriere_le_proxy_l_application_ne_double_pas_ses_en_tetes(): void
    {
        config(['app.entetes_poses_par_le_proxy' => true]);

        $reponse = $this->get(route('login'))->assertOk();

        foreach (array_keys(EnTetesSecurite::entetesCommunsAvecLeProxy()) as $entete) {
            $this->assertFalse($reponse->headers->has($entete), "{$entete} est posé par le proxy ANPTIC");
        }
        $this->assertTrue($reponse->headers->has('Content-Security-Policy'));
    }

    public function test_sans_proxy_chaque_en_tete_est_pose_une_seule_fois(): void
    {
        config(['app.entetes_poses_par_le_proxy' => false]);

        $reponse = $this->get(route('login'))->assertOk();

        foreach (EnTetesSecurite::entetesCommunsAvecLeProxy() as $entete => $valeur) {
            $this->assertSame([$valeur], $reponse->headers->all(strtolower($entete)), $entete);
        }
    }

    public function test_le_htaccess_ne_pose_aucun_en_tete_de_securite(): void
    {
        $htaccess = File::get(public_path('.htaccess'));

        foreach ([...array_keys(EnTetesSecurite::entetesCommunsAvecLeProxy()), 'Strict-Transport-Security'] as $entete) {
            $this->assertDoesNotMatchRegularExpression('/^\s*Header\s+(set|add|always)[^\n]*'.preg_quote($entete, '/').'/mi', $htaccess, $entete);
        }
    }

    // ── Vul-005 : CSP stricte ──────────────────────────────────────────────

    public function test_vul005_ni_script_src_ni_style_src_n_autorisent_unsafe_inline(): void
    {
        $csp = $this->get(route('login'))->assertOk()->headers->get('Content-Security-Policy');

        preg_match_all('/(?:^|;\s*)(script-src|style-src)\s([^;]*)/', $csp, $directives, PREG_SET_ORDER);
        $this->assertCount(2, $directives, $csp);
        foreach ($directives as [, $nom, $valeur]) {
            $this->assertStringNotContainsString("'unsafe-inline'", $valeur, $nom);
            $this->assertStringNotContainsString("'unsafe-eval'", $valeur, $nom);
            $this->assertMatchesRegularExpression("/'nonce-[^']+'/", $valeur, $nom);
        }
        $this->assertStringContainsString("style-src-attr 'unsafe-inline'", $csp);
        $this->assertFalse(array_key_exists('csp_scripts_observation', config('app')), 'plus de mode observation');
    }

    public function test_vul005_chaque_balise_script_et_style_de_la_page_porte_le_nonce(): void
    {
        foreach ([route('login'), route('public.home')] as $url) {
            $reponse = $this->get($url)->assertOk();
            preg_match("/script-src 'self' 'nonce-([^']+)'/", $reponse->headers->get('Content-Security-Policy'), $m);

            preg_match_all('/<(script|style)\b(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/i', $reponse->getContent(), $balises);
            foreach ($balises[0] as $balise) {
                $this->assertStringContainsString('nonce="'.$m[1].'"', $balise, "{$url} : {$balise}");
            }
        }
    }

    public function test_vul005_aucune_vue_n_a_de_style_sans_nonce(): void
    {
        foreach (File::allFiles(resource_path('views')) as $vue) {
            $chemin = $vue->getRelativePathname();
            // Gabarits PDF (dompdf, côté serveur) : jamais servis au navigateur.
            if (str_starts_with($chemin, 'pdf'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            $source = preg_replace('/\{\{--.*?--\}\}|<!--.*?-->/s', '', $vue->getContents());
            $this->assertDoesNotMatchRegularExpression('/<style\b(?![^>]*\bnonce=)[^>]*>/i', $source, "{$chemin} : <style> sans nonce");
        }
    }

    public function test_vul005_aucune_bibliotheque_n_injecte_de_style_sans_nonce(): void
    {
        $this->assertFileDoesNotExist(public_path('assets/vendors/sweetalert2/sweetalert2.all.min.js'), 'SweetAlert2 « .all » injecte un <style> sans nonce');
        $this->assertStringContainsString('sweetalert2.min.css', File::get(resource_path('views/partials/alertes.blade.php')));
    }
}
