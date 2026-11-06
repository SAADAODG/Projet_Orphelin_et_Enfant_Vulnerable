<?php

/**
 * Crée (ou recrée) la base de performance et y installe le schéma et les données de référence :
 *   php scripts/perf/preparer-base.php            → crée plateforme_oev_perf si besoin, migrate:fresh --seed
 *
 * Seule une base dont le nom se termine par « _perf » est acceptée (voir _amorce.php).
 */

require_once __DIR__ . '/_amorce.php';

$base = perf_nom_base();

// Création de la base si elle n'existe pas (connexion à « postgres », jamais à la base de développement)
$admin = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', perf_mot_de_passe_bd(), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$requete = $admin->prepare('select 1 from pg_database where datname = ?');
$requete->execute([$base]);
if (! $requete->fetchColumn()) {
    // Un nom de base ne peut pas être un paramètre lié : il est validé strictement avant d'être inséré
    if (! preg_match('/^[a-zA-Z0-9_]+$/', $base)) {
        fwrite(STDERR, "REFUS : nom de base invalide « {$base} » (lettres, chiffres et _ seulement).
");
        exit(2);
    }
    $admin->exec('CREATE DATABASE "' . $base . '"');
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
