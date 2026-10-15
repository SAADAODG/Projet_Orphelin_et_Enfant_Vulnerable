<?php

/**
 * Profileur côté serveur : rejoue UNE requête GET dans le processus, sur la base _perf, en tant qu'un
 * utilisateur, et décompose le temps.
 *
 *   php scripts/perf/profiler.php <url> [email] [--json]
 *   ex : php scripts/perf/profiler.php /admin/liste-oev agent@oev.gov.bf
 *
 * Mesures : démarrage du framework, connexion BD, nombre / durée des requêtes SQL, requêtes les plus
 * lentes, requêtes répétées (signe d'un N+1), vues rendues, pic mémoire, taille de la réponse.
 * Les exports en flux (StreamedResponse) sont réellement produits pour mesurer leur taille.
 */

$t0 = microtime(true);
require __DIR__ . '/_amorce.php';

$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => $a !== '--json'));
$json = in_array('--json', $argv, true);
$url = $args[0] ?? '/admin/dashboard';
$email = $args[1] ?? 'admin@oev.gov.bf';

$app = perf_application();
// Essai d'un réglage PostgreSQL pour cette seule connexion (ex : PERF_PG_SET="random_page_cost = 1.1")
if ($reglage = getenv('PERF_PG_SET')) {
    Illuminate\Support\Facades\DB::statement('SET ' . $reglage);
}
$demarrage = microtime(true) - $t0;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Connexion BD mesurée à part (ouverte avant la requête)
$t = microtime(true);
DB::connection()->getPdo();
$connexion = microtime(true) - $t;

$utilisateur = App\Models\User::where('email', $email)->firstOrFail();

$requetes = [];
DB::listen(function ($q) use (&$requetes) {
    $requetes[] = ['sql' => $q->sql, 'ms' => $q->time];
});
$vues = [];
View::composer('*', function ($vue) use (&$vues) {
    $vues[$vue->name()] = ($vues[$vue->name()] ?? 0) + 1;
});

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app['auth']->guard('web')->setUser($utilisateur);
$requete = Illuminate\Http\Request::create($url, 'GET');
$requete->setLaravelSession($app['session']->driver());

$memoireAvant = memory_get_peak_usage(true);
$t = microtime(true);
$erreur = null;
try {
    $reponse = $kernel->handle($requete);
    // Contenu réel : y compris pour un export produit en flux
    if ($reponse instanceof StreamedResponse) {
        ob_start();
        $reponse->sendContent();
        $taille = strlen((string) ob_get_clean());
    } elseif ($reponse instanceof BinaryFileResponse) {
        $taille = $reponse->getFile()->getSize();
    } else {
        $taille = strlen((string) $reponse->getContent());
    }
    $statut = $reponse->getStatusCode();
    $exception = $reponse->exception ?? null;
    if ($exception) {
        $erreur = get_class($exception) . ' : ' . mb_substr($exception->getMessage(), 0, 200);
    }
} catch (Throwable $e) {
    $statut = 0;
    $taille = 0;
    $erreur = get_class($e) . ' : ' . mb_substr($e->getMessage(), 0, 200);
}
$traitement = microtime(true) - $t;

$parSql = collect($requetes)->groupBy('sql');
$resultat = [
    'url' => $url,
    'utilisateur' => $email,
    'statut' => $statut,
    'erreur' => $erreur,
    'demarrage_ms' => round($demarrage * 1000),
    'connexion_bd_ms' => round($connexion * 1000, 1),
    'traitement_ms' => round($traitement * 1000),
    'sql_nombre' => count($requetes),
    'sql_ms' => round(collect($requetes)->sum('ms')),
    'sql_plus_lentes' => collect($requetes)->sortByDesc('ms')->take(3)->map(fn ($q) => round($q['ms']) . ' ms · ' . mb_substr($q['sql'], 0, 140))->values()->all(),
    'sql_repetees' => $parSql->filter(fn ($l) => $l->count() >= 5)->map(fn ($l, $sql) => $l->count() . ' × ' . mb_substr($sql, 0, 120))->values()->take(5)->all(),
    'vues' => array_sum($vues),
    'vues_distinctes' => count($vues),
    'memoire_pic_mo' => round(max($memoireAvant, memory_get_peak_usage(true)) / 1048576, 1),
    'taille_ko' => round($taille / 1024),
];

if ($json) {
    echo json_encode($resultat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit;
}

foreach ($resultat as $cle => $valeur) {
    if (is_array($valeur)) {
        echo str_pad($cle, 18), ":\n";
        foreach ($valeur as $ligne) {
            echo '    ', $ligne, "\n";
        }
    } else {
        echo str_pad($cle, 18), ': ', $valeur ?? '—', "\n";
    }
}
