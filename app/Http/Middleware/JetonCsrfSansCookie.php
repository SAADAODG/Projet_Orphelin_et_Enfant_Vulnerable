<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * Protection CSRF sans le cookie XSRF-TOKEN (pré-scan ANSSI du 25/11/2026 et scan OWASP ZAP :
 * « Cookie No HttpOnly Flag »).
 *
 * Laravel pose ce cookie volontairement lisible par JavaScript, pour les appels AJAX (axios).
 * OEV n'en fait aucun en écriture : chaque formulaire envoie son jeton par le champ caché @csrf,
 * et les fetch() existants (localités, écoles, centres, recherche, pastille) sont des GET.
 * Le jeton de session reste vérifié comme avant.
 *
 * Un futur appel AJAX en POST enverra csrf_token() par l'en-tête X-CSRF-TOKEN, sans revenir
 * au cookie.
 */
class JetonCsrfSansCookie extends PreventRequestForgery
{
    protected $addHttpCookie = false;
}
