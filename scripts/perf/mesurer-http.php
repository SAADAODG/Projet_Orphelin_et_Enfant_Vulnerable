<?php

/**
 * Temps de réponse HTTP réels : connexion avec jeton CSRF, puis une liste de pages, plusieurs fois.
 *
 *   php scripts/perf/mesurer-http.php [url-de-base] [repetitions]
 *   ex : php scripts/perf/mesurer-http.php http://127.0.0.1:8098 5
 *
 * Par défaut : http://127.0.0.1:8098, le serveur de PERFORMANCE (base _perf) lancé par
 *   DB_DATABASE=plateforme_oev_perf php artisan serve --host=127.0.0.1 --port=8098
 * Viser le serveur de développement (port 8000) écrirait dans la base de développement (session,
 * journal de connexion) : c'est refusé sauf avec PERF_AUTORISER_DEV=1.
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8098', '/');
$repetitions = max(1, (int) ($argv[2] ?? 3));
$email = getenv('PERF_EMAIL') ?: 'agent@oev.gov.bf';
$motDePasse = getenv('PERF_MOT_DE_PASSE') ?: 'admin1234';
if (str_contains($base, ':8000') && getenv('PERF_AUTORISER_DEV') !== '1') {
    fwrite(STDERR, "REFUS : le port 8000 sert la base de développement. Lancez le serveur de performance (port 8098) ou PERF_AUTORISER_DEV=1.\n");
    exit(2);
}

$cookies = tempnam(sys_get_temp_dir(), 'perf-cookies');
$appel = function (string $url, ?array $post = null) use ($cookies): array {
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    if ($post !== null) {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $corps = (string) curl_exec($c);
    $info = curl_getinfo($c);

    return [$info['http_code'], $info['total_time'], strlen($corps), $corps, $info['redirect_url'] ?? ''];
};

// Connexion : jeton CSRF de la page de connexion, puis POST
[, , , $page] = $appel("{$base}/login");
if (! preg_match('/name="_token" value="([^"]+)"/', $page, $m)) {
    fwrite(STDERR, "Jeton CSRF introuvable sur {$base}/login\n");
    exit(1);
}
[$code, , , , $redirection] = $appel("{$base}/login", ['_token' => $m[1], 'email' => $email, 'password' => $motDePasse]);
if ($code !== 302 || str_contains($redirection, '/login')) {
    fwrite(STDERR, "Connexion refusée pour {$email} (HTTP {$code}).\n");
    exit(1);
}
echo "Connecté : {$email} sur {$base} · {$repetitions} répétition(s)\n\n";

$pages = ['/admin/dashboard', '/admin/liste-oev', '/admin/oevs?etat=tous', '/admin/validation-dossiers?etat=tous', '/admin/integration-oev?etat=tous',
    '/admin/oevs-sortants', '/admin/filtrage-extraction', '/admin/journal', '/admin/parrainage/sessions', '/admin/parrainage/appuis',
    '/admin/parrainage/pilotage', '/admin/parrainage/suivis', '/admin/signalements'];

printf("%-42s %6s %8s %8s %8s %8s\n", 'Page', 'HTTP', 'min ms', 'moy ms', 'max ms', 'Ko');
foreach ($pages as $chemin) {
    $temps = [];
    foreach (range(1, $repetitions) as $_) {
        [$code, $duree, $taille] = $appel($base . $chemin);
        $temps[] = $duree * 1000;
    }
    printf("%-42s %6s %8.0f %8.0f %8.0f %8.0f\n", $chemin, $code, min($temps), array_sum($temps) / count($temps), max($temps), $taille / 1024);
}
@unlink($cookies);
