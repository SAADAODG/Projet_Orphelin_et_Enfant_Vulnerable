<?php

use App\Exceptions\RetourApresErreur;
use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\JetonCsrfSansCookie;
use App\Http\Middleware\RefuserRequeteTronquee;
use App\Support\HotesAutorises;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        $middleware->append(EnTetesSecurite::class);

        // Rapport ANSSI du 17/11/2026, Vul-001 : requête tronquée par PHP (max_input_vars) refusée.
        $middleware->prepend(RefuserRequeteTronquee::class);

        // Vul-002 et Vul-003 : derrière le reverse proxy HTTPS de l'ANPTIC, son IP (TRUSTED_PROXIES)
        // est de confiance pour l'IP du client, le schéma (https) et le port ; JAMAIS pour
        // X-Forwarded-Host, que le proxy relaie tel que le client l'a envoyé.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)),
                headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        // CSRF par le champ caché @csrf, sans le cookie XSRF-TOKEN lisible par JavaScript (pas HttpOnly).
        $middleware->replaceInGroup('web', PreventRequestForgery::class, JetonCsrfSansCookie::class);

        // Vul-002 : en-tête Host hors de la liste (hôte d'APP_URL + TRUSTED_HOSTS) → 400.
        // Laravel ne l'applique ni en local ni pendant les tests.
        $middleware->trustHosts(at: fn () => HotesAutorises::motifs(), subdomains: false);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Accès refusé, élément introuvable, session expirée… : retour à la page précédente avec une alerte
        $exceptions->render(fn (Throwable $e, Request $request) => RetourApresErreur::reponse($e, $request));
    })->create();
