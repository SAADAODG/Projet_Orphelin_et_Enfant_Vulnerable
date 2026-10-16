<?php

/**
 * Applique les migrations en attente à la base de PERFORMANCE uniquement (jamais à la base de développement).
 *
 *   php scripts/perf/migrer.php
 */

require __DIR__ . '/_amorce.php';

$app = perf_application();
$debut = microtime(true);
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo Illuminate\Support\Facades\Artisan::output();
perf_pdo()->exec('analyze');
printf("Base %s migrée en %.1f s\n", perf_nom_base(), microtime(true) - $debut);
