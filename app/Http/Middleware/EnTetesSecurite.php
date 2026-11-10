<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP ajoutés à toutes les réponses de l'application.
 *
 * Scripts : seuls les fichiers du site et les scripts écrits dans les vues qui portent le nonce de la
 * page (<script nonce="{{ $cspNonce }}">) s'exécutent. Les attributs onclick/onchange et les liens
 * « javascript: » sont donc interdits. Le nonce change à chaque requête.
 *
 * Styles : 'unsafe-inline' reste toléré pour l'instant (balises <style> et attributs style des vues).
 *
 * La CSP interdit aussi toute ressource externe, les plugins et l'affichage du site dans un cadre
 * d'un autre domaine.
 */
class EnTetesSecurite
{
    private const EN_TETES = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Avant $next : les vues de la page doivent recevoir le nonce
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        // Ne pas annoncer la version de PHP (l'en-tête est ajouté par PHP lui-même, hors de la réponse Symfony)
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        foreach (self::EN_TETES as $nom => $valeur) {
            if (! $response->headers->has($nom)) {
                $response->headers->set($nom, $valeur);
            }
        }

        // CSP réservée aux pages HTML : appliquée à un PDF ouvert dans le navigateur, elle peut bloquer la visionneuse
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $response->headers->has('Content-Security-Policy')) {
            $stricte = self::csp("'self' 'nonce-{$nonce}'");

            if (config('app.csp_scripts_observation')) {
                // Déploiement progressif : ancienne règle appliquée, règle stricte seulement observée
                $response->headers->set('Content-Security-Policy', self::csp("'self' 'unsafe-inline'"));
                $response->headers->set('Content-Security-Policy-Report-Only', $stricte);
            } else {
                $response->headers->set('Content-Security-Policy', $stricte);
            }
        }

        if ($request->secure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private static function csp(string $sourcesScripts): string
    {
        return "default-src 'self'; "
            . "script-src {$sourcesScripts}; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'self'";
    }
}
