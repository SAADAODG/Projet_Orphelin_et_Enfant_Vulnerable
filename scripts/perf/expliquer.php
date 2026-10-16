<?php

/**
 * Plan d'exécution (EXPLAIN ANALYZE) des requêtes d'une page, sur la base de PERFORMANCE : rejoue la page
 * comme profiler.php et affiche le plan des N requêtes les plus lentes.
 *
 *   php scripts/perf/expliquer.php <url> [email] [N]
 */

require __DIR__ . '/_amorce.php';

$url = $argv[1] ?? '/admin/dashboard';
$email = $argv[2] ?? 'agent@oev.gov.bf';
$n = (int) ($argv[3] ?? 3);

$app = perf_application();
$requetes = [];
Illuminate\Support\Facades\DB::listen(function ($q) use (&$requetes) {
    $requetes[] = ['sql' => $q->sql, 'brut' => $q->toRawSql(), 'liaisons' => $q->connection->prepareBindings($q->bindings), 'ms' => $q->time];
});
$app['auth']->guard('web')->setUser(App\Models\User::where('email', $email)->firstOrFail());
$requete = Illuminate\Http\Request::create($url, 'GET');
$requete->setLaravelSession($app['session']->driver());
$app->make(Illuminate\Contracts\Http\Kernel::class)->handle($requete);

usort($requetes, fn ($a, $b) => $b['ms'] <=> $a['ms']);
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
foreach (array_slice($requetes, 0, $n) as $r) {
    echo str_repeat('=', 100), "\n", round($r['ms']), " ms · {$r['sql']}\n\n";
    if (! str_starts_with(strtolower(ltrim($r['sql'])), 'select')) {
        continue;
    }
    // Requête préparée avec ses vrais paramètres (les booléens restent des booléens)
    $plan = $pdo->prepare('explain (analyze, buffers) ' . $r['sql']);
    foreach (array_values($r['liaisons']) as $i => $valeur) {
        $plan->bindValue($i + 1, $valeur, match (true) { is_bool($valeur) => PDO::PARAM_BOOL, is_int($valeur) => PDO::PARAM_INT, $valeur === null => PDO::PARAM_NULL, default => PDO::PARAM_STR });
    }
    $plan->execute();
    foreach ($plan->fetchAll(PDO::FETCH_COLUMN) as $ligne) {
        echo "  {$ligne}\n";
    }
}
