<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Aucune page d'erreur brute pour l'utilisateur : un accès refusé, un élément introuvable, une session
 * expirée ou une action impossible le ramènent sur la page précédente, avec une alerte SweetAlert
 * (partials/alertes) qui explique pourquoi.
 *
 * Les erreurs serveur (500) ne sont redirigées qu'en production et après l'envoi d'un formulaire :
 * sur une simple page, rediriger risquerait une boucle, la page d'erreur errors/500 est affichée.
 * En mode debug, la page de diagnostic de Laravel est conservée.
 */
final class RetourApresErreur
{
    public static function reponse(Throwable $e, Request $request): ?RedirectResponse
    {
        $statut = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        $destination = self::traitementStandard($e, $request, $statut) ? null : self::destination($request);
        if ($destination === null) {
            return null;
        }

        [$type, $message] = self::alerte($e, $statut);
        $redirection = redirect()->to($destination)->with($type, $message);

        // Après l'envoi d'un formulaire, les données saisies sont conservées (sauf les mots de passe)
        return $request->isMethod('GET')
            ? $redirection
            : $redirection->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']));
    }

    /**
     * Laissé au traitement standard de Laravel : appels AJAX / JSON, connexion requise et erreurs de formulaire ;
     * erreur serveur en mode debug ou sur une simple page.
     */
    private static function traitementStandard(Throwable $e, Request $request, int $statut): bool
    {
        return $request->expectsJson() || $e instanceof AuthenticationException || $e instanceof ValidationException
            || ($statut >= 500 && (config('app.debug') || $request->isMethod('GET')));
    }

    /** @return array{0: string, 1: string} type d'alerte (warning, error) et message */
    private static function alerte(Throwable $e, int $statut): array
    {
        return match (true) {
            $statut === 403 => ['warning', 'Vous n’avez pas accès à cette page ou à cette action.'],
            $statut === 404 => ['warning', 'La page ou l’élément demandé est introuvable : il a peut-être été supprimé ou déplacé.'],
            $statut === 419 => ['warning', 'Votre session a expiré. Veuillez recommencer l’opération.'],
            $statut === 429 => ['warning', 'Trop de tentatives. Patientez un instant avant de réessayer.'],
            // abort(422, '…') / abort(409, '…') du code de l'application : message rédigé pour l'utilisateur
            in_array($statut, [409, 422], true) && $e->getMessage() !== '' => ['warning', $e->getMessage()],
            $statut >= 400 && $statut < 500 => ['warning', 'Cette action n’est pas possible.'],
            default => ['error', 'Une erreur inattendue est survenue. Réessayez ; si le problème persiste, contactez l’administrateur.'],
        };
    }

    /** Page précédente, sinon l'accueil de l'espace concerné ; jamais la page qui vient d'échouer. */
    private static function destination(Request $request): ?string
    {
        $actuelle = $request->fullUrl();
        // Pas url()->previous() : sans page précédente, il renvoie la racine du site (accueil public)
        $precedente = $request->headers->get('referer');
        if (! $precedente) {
            $precedente = $request->hasSession() ? $request->session()->previousUrl() : '';
        }
        $precedente = (string) $precedente;
        $precedente = $precedente !== '' ? url($precedente) : '';

        $candidates = [
            $precedente !== '' && $precedente !== $actuelle && str_starts_with($precedente, url('/')) ? $precedente : null,
            $request->user() ? route('dashboard') : route('public.home'),
        ];

        foreach ($candidates as $url) {
            if ($url && $url !== $actuelle) {
                return $url;
            }
        }

        return null;
    }
}
