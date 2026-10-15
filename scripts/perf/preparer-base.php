<?php

/**
 * Crée (ou recrée) la base de performance et y installe le schéma et les données de référence :
 *   php scripts/perf/preparer-base.php            → crée plateforme_oev_perf si besoin, migrate:fresh --seed
 *
 * Seule une base dont le nom se termine par « _perf » est acceptée (voir _amorce.php).
 */

require __DIR__ . '/_amorce.php';

$base = perf_nom_base();

// Création de la base si elle n'existe pas (connexion à « postgres », jamais à la base de développement)
$admin = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', getenv('PERF_DB_PASSWORD') ?: '1234', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$existe = $admin->query("select 1 from pg_database where datname = " . $admin->quote($base))->fetchColumn();
if (! $existe) {
    $admin->exec('CREATE DATABASE "' . str_replace('"', '', $base) . '"');
    echo "Base {$base} créée.\n";
}
$admin = null;

$app = perf_application();
echo 'Base utilisée : ' . $app['db']->connection()->getDatabaseName() . "\n";

$debut = microtime(true);
$code = Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
echo Illuminate\Support\Facades\Artisan::output();
printf("migrate:fresh --seed : code %d, %.1f s\n", $code, microtime(true) - $debut);
exit($code);
