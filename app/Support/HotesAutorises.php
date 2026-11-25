<?php

namespace App\Support;

/**
 * Hôtes sous lesquels la plateforme accepte d'être appelée (rapport ANSSI d'OEV du 17/11/2026, Vul-002
 * « Injection d'en-tête hôte »). Une requête dont l'en-tête Host n'est pas dans cette liste reçoit
 * une 400 (TrustHosts, bootstrap/app.php) : elle ne peut ni empoisonner un cache ni fabriquer
 * un lien vers un domaine de l'attaquant.
 *
 * Source : l'hôte d'APP_URL, plus TRUSTED_HOSTS (noms ou IP séparés par des virgules), par
 * exemple l'IP de la VM pour l'instance de formation appelée sans nom de domaine.
 */
class HotesAutorises
{
    /**
     * @return list<string> motifs exacts au format attendu par Request::setTrustedHosts()
     */
    public static function motifs(): array
    {
        $hotes = [parse_url((string) config('app.url'), PHP_URL_HOST)];

        foreach (explode(',', (string) config('app.trusted_hosts')) as $hote) {
            $hotes[] = trim($hote);
        }

        return array_values(array_unique(array_map(
            fn (string $hote) => '^'.preg_quote(strtolower($hote)).'$',
            array_filter($hotes, fn ($hote) => is_string($hote) && $hote !== ''),
        )));
    }
}
