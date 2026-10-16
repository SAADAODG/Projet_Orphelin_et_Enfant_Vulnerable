<?php

/**
 * Socle commun des scripts de mesure : démarre Laravel sur la base de PERFORMANCE uniquement.
 *
 * Garde-fou : le nom de la base vient de PERF_DB (défaut plateforme_oev_perf) et DOIT se terminer
 * par « _perf ». Toute autre base est refusée avant la moindre requête : la base de développement
 * (plateforme_oev) n'est jamais touchée par ces scripts.
 */

const PERF_BASE_DEFAUT = 'plateforme_oev_perf';

function perf_nom_base(): string
{
    $base = getenv('PERF_DB') ?: PERF_BASE_DEFAUT;
    if (! str_ends_with($base, '_perf')) {
        fwrite(STDERR, "REFUS : la base « {$base} » ne se termine pas par « _perf ». Les scripts de mesure n'utilisent que des bases de performance.\n");
        exit(2);
    }

    return $base;
}

/** Variables d'environnement imposées AVANT le démarrage de Laravel (elles priment sur .env). */
function perf_environnement(): void
{
    $base = perf_nom_base();
    foreach (['DB_DATABASE' => $base, 'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'LOG_LEVEL' => 'error'] as $cle => $valeur) {
        putenv("{$cle}={$valeur}");
        $_ENV[$cle] = $_SERVER[$cle] = $valeur;
    }
}

/** Démarre l'application et vérifie, connexion ouverte, que l'on est bien sur la base _perf. */
function perf_application(): Illuminate\Foundation\Application
{
    perf_environnement();
    $racine = dirname(__DIR__, 2);
    require_once $racine . '/vendor/autoload.php';
    $app = require $racine . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    perf_verifier_base($app);

    return $app;
}

function perf_verifier_base(Illuminate\Foundation\Application $app): void
{
    $reelle = $app['db']->connection()->getDatabaseName();
    if ($reelle !== perf_nom_base() || ! str_ends_with((string) $reelle, '_perf')) {
        fwrite(STDERR, "REFUS : connexion ouverte sur « {$reelle} » au lieu de « " . perf_nom_base() . " ». Arrêt.\n");
        exit(2);
    }
}

/** Connexion PDO directe à la base _perf (générateur SQL), sans passer par Laravel. */
function perf_pdo(?string $base = null): PDO
{
    $base ??= perf_nom_base();
    $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname={$base}", 'postgres', getenv('PERF_DB_PASSWORD') ?: '1234', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $reelle = $pdo->query('select current_database()')->fetchColumn();
    if (! str_ends_with((string) $reelle, '_perf')) {
        fwrite(STDERR, "REFUS : base « {$reelle} ».\n");
        exit(2);
    }

    return $pdo;
}
