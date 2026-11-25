<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapport ANSSI d'OEV du 17/11/2026, Vul-001 « Nombre de variables d'entrée PHP dépassé ».
 *
 * Au-delà de max_input_vars (1 000), PHP supprime SANS ERREUR les champs en trop et lève un
 * avertissement au démarrage de la requête. L'application traiterait alors un formulaire
 * incomplet (sélection de parrainage tronquée, paiement sans une partie des feuilles…). On
 * refuse la requête en 400 ; l'avertissement, lui, ne doit jamais être affiché
 * (display_errors=Off dans le php.ini du serveur, voir public/.user.ini) mais journalisé.
 *
 * Revue de code : le plus gros formulaire fixe compte 22 champs (fiche OEV, suivi) ; les listes
 * de cases restent bornées : sélection de parrainage 50 enfants par page + 13 régions et
 * 45 provinces. Aucun écran légitime n'approche la limite.
 */
class RefuserRequeteTronquee
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->requeteTronquee()) {
            abort(400, 'Requête trop volumineuse.');
        }

        return $next($request);
    }

    /** L'avertissement de PHP est levé avant Laravel : seul error_get_last() le garde. */
    protected function requeteTronquee(): bool
    {
        $erreur = error_get_last();

        return $erreur !== null && str_contains($erreur['message'], 'Input variables exceeded');
    }
}
