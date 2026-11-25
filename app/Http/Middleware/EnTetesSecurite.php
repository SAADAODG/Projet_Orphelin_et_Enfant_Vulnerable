<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP ajoutés aux réponses de l'application.
 *
 * Rapport ANSSI du 17/11/2026 (oev-05, ANO ajourné) :
 *  - Vul-004 « HSTS permissive » : deux Strict-Transport-Security, celui de l'application et celui
 *    du reverse proxy ANPTIC. HSTS n'est posé QUE par le proxy : jamais ici.
 *  - Vul-005 « CSP permissive » : plus de 'unsafe-inline' dans script-src ni dans style-src. Seuls
 *    les fichiers du site et les <script>/<style> qui portent le nonce de la page
 *    (nonce="{{ $cspNonce }}") s'exécutent. Le nonce change à chaque requête. Attributs
 *    onclick/onchange et liens « javascript: » interdits. Seul style-src-attr 'unsafe-inline'
 *    reste toléré (attributs style="" des vues) : un attribut style n'exécute aucun script.
 *  - En-têtes en double (même reproche que sociale-03, VUL-004) : le proxy ANPTIC pose déjà
 *    X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, COOP et CORP.
 *    Derrière lui (SECURITY_HEADERS_BY_PROXY, vrai par défaut dès que TRUSTED_PROXIES est
 *    renseigné), l'application ne les pose pas.
 *
 * La CSP interdit aussi toute ressource externe, les plugins et l'affichage du site dans un cadre
 * d'un autre domaine.
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        // Avant $next : les vues de la page doivent recevoir le nonce
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        // Ne pas annoncer la version de PHP (l'en-tête est ajouté par PHP lui-même, hors de la réponse Symfony)
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        if (! config('app.entetes_poses_par_le_proxy')) {
            foreach (self::entetesCommunsAvecLeProxy() as $nom => $valeur) {
                $response->headers->set($nom, $valeur);
            }
        }

        // CSP réservée aux pages HTML : appliquée à un PDF ouvert dans le navigateur, elle peut bloquer la visionneuse
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', self::csp($nonce));
        }

        // Jamais de Strict-Transport-Security ici : le reverse proxy ANPTIC le pose (Vul-004).
        $response->headers->remove('Strict-Transport-Security');

        return $response;
    }

    /**
     * En-têtes que le proxy ANPTIC pose lui-même sur toutes les réponses (mesuré le 25/11/2026).
     *
     * @return array<string, string>
     */
    public static function entetesCommunsAvecLeProxy(): array
    {
        return [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];
    }

    private static function csp(string $nonce): string
    {
        return "default-src 'self'; "
            . "script-src 'self' 'nonce-{$nonce}'; "
            . "style-src 'self' 'nonce-{$nonce}'; "
            . "style-src-attr 'unsafe-inline'; "
            . "img-src 'self' data: blob:; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'self'";
    }
}
