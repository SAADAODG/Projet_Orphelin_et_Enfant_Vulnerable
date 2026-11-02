<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP ajoutés à toutes les réponses de l'application.
 *
 * La CSP tolère les scripts et styles écrits dans les vues ('unsafe-inline') : de nombreuses pages
 * en contiennent. Elle interdit en revanche toute ressource externe, les plugins et l'affichage
 * du site dans un cadre d'un autre domaine.
 */
class EnTetesSecurite
{
    private const CSP = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob:; "
        . "font-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self'";

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
            $response->headers->set('Content-Security-Policy', self::CSP);
        }

        if ($request->secure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
