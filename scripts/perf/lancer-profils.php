<?php

/**
 * Lanceur : profile chaque page et chaque export dans un PROCESSUS SÉPARÉ (scripts/perf/profiler.php),
 * avec la vraie limite mémoire et la vraie limite de durée, pour qu'un plantage n'empêche pas de mesurer
 * la suite. Plusieurs profils d'utilisateur (l'application cloisonne les données par zone).
 *
 *   php scripts/perf/lancer-profils.php [fichier-resultat.json] [--profils=agent,dr,dp] [--filtre=texte]
 *
 * Tableau récapitulatif trié par nombre de requêtes SQL ; résultats complets dans le fichier JSON.
 */

require __DIR__ . '/_amorce.php';
require __DIR__ . '/../../vendor/autoload.php';

$memoire = getenv('PERF_MEMORY_LIMIT') ?: '128M';   // valeur de php.ini pour le serveur web
$duree = (int) (getenv('PERF_MAX_EXECUTION') ?: 30); // max_execution_time de php.ini
$sortie = null;
$profils = ['admin' => 'admin@oev.gov.bf', 'agent' => 'agent@oev.gov.bf', 'dr' => 'dr@oev.gov.bf', 'dp' => 'dp@oev.gov.bf'];
$filtre = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--profils=')) {
        $profils = array_intersect_key($profils, array_flip(explode(',', substr($arg, 10))));
    } elseif (str_starts_with($arg, '--filtre=')) {
        $filtre = substr($arg, 9);
    } else {
        $sortie = $arg;
    }
}

// Identifiants réels de la base _perf (fiches)
$pdo = perf_pdo();
$id = fn (string $sql) => $pdo->query($sql)->fetchColumn() ?: 0;
$session = $id("select max(id) from sessions_parrainage where etat <> 'en_cours'");
$sessionEnCours = $id("select max(id) from sessions_parrainage where etat = 'en_cours'") ?: $session;
$oev = $id("select max(id) from oevs where statut_dossier = 'integre' and desactivation_etat is null and province_id = coalesce((select province_id from users where email = 'dp@oev.gov.bf'), province_id)");
$parrain = $id('select min(id) from parrains');
$brouillon = $id("select max(id) from oevs where statut_dossier = 'brouillon' and province_id = coalesce((select province_id from users where email = 'dp@oev.gov.bf'), province_id)");
$commune = $id("select commune_id from ecoles group by commune_id order by count(*) desc limit 1");
$codeRare = substr((string) $pdo->query('select code from oevs where code is not null order by id desc offset 777 limit 1')->fetchColumn(), -8);

$pages = [
    'Tableau de bord' => '/admin/dashboard',
    'Liste des OEV' => '/admin/liste-oev',
    'Liste des OEV (recherche)' => '/admin/liste-oev?q=sank',
    'OEV sortants' => '/admin/oevs-sortants',
    'Constituer dossier (en cours)' => '/admin/oevs?etat=tous',
    'Constituer dossier (intégrés)' => '/admin/oevs?vue=integres',
    'Validation des dossiers' => '/admin/validation-dossiers?etat=tous',
    'Intégration des OEV' => '/admin/integration-oev?etat=tous',
    'Liste des OEV (recherche rare)' => "/admin/liste-oev?q={$codeRare}",
    'Fiche OEV' => "/admin/oevs/{$oev}",
    'Modifier un dossier' => "/admin/oevs/{$brouillon}/edit",
    'Nouveau dossier' => '/admin/oevs/create',
    'Filtrage et extraction' => '/admin/filtrage-extraction',
    'Export filtrage (Excel)' => '/admin/filtrage-extraction/export?format=xlsx&colonnes[]=Code%20OEV&colonnes[]=Nom&colonnes[]=Sexe&colonnes[]=R%C3%A9gion',
    'Export filtrage région (Excel)' => '/admin/filtrage-extraction/export?format=xlsx&region_id=' . $id("select region_id from users where email = 'dr@oev.gov.bf'") . '&' . implode('&', array_map(fn ($c) => 'colonnes[]=' . rawurlencode($c), App\Http\Controllers\ExtractionController::COLONNES_PAR_DEFAUT)),
    'Filtrage (recherche rare)' => "/admin/filtrage-extraction?q={$codeRare}",
    'Signalements' => '/admin/signalements',
    'Plaintes' => '/admin/plaintes',
    'Utilisateurs' => '/admin/users',
    'Rôles et permissions' => '/admin/roles-permissions',
    'Paramètres' => '/admin/parametres',
    'Écoles' => '/admin/parametres/ecoles',
    'Écoles (recherche)' => '/admin/parametres/ecoles?q=saint',
    'Liste de référence' => '/admin/parametres/listes/groupes-population',
    'Écoles d’une commune (formulaire)' => "/admin/localites/communes/{$commune}/ecoles",
    'Régions' => '/admin/localites/regions',
    'Provinces' => '/admin/localites/provinces',
    'Communes' => '/admin/localites/communes',
    'Villages' => '/admin/localites/villages',
    'Journal d’audit' => '/admin/journal',
    'Export journal (Excel)' => '/admin/journal/export',
    'Export journal 4 mois (Excel)' => '/admin/journal/export?du=' . date('Y-m-d', strtotime('-120 days')),
    'Sessions de parrainage' => '/admin/parrainage/sessions',
    'Fiche de session' => "/admin/parrainage/sessions/{$session}",
    'Sélection : liste' => "/admin/parrainage/sessions/{$session}/selection?onglet=liste",
    'Sélection : éligibles' => "/admin/parrainage/sessions/{$sessionEnCours}/selection?onglet=eligibles",
    'Sélection : éligibles (recherche)' => "/admin/parrainage/sessions/{$sessionEnCours}/selection?onglet=eligibles&q=sank",
    'Suivi des parrainés' => '/admin/parrainage/suivis',
    'Parrains' => '/admin/parrainage/parrains',
    'Fiche parrain' => "/admin/parrainage/parrains/{$parrain}",
    'Appuis' => '/admin/parrainage/appuis',
    'Pilotage : tableau de bord' => '/admin/parrainage/pilotage',
    'Pilotage : extractions' => "/admin/parrainage/pilotage/extractions?parrain_id={$parrain}",
    'Export paiement (Excel)' => "/admin/parrainage/pilotage/extractions/paiement?session_id={$session}&format=xlsx",
    'Export liste parrain (Excel)' => "/admin/parrainage/pilotage/extractions/parrain?parrain_id={$parrain}&format=xlsx&filtre=1&source=eligibles",
];
if ($filtre) {
    $pages = array_filter($pages, fn ($u, $nom) => stripos($nom, $filtre) !== false, ARRAY_FILTER_USE_BOTH);
}

$php = PHP_BINARY;
$profiler = __DIR__ . '/profiler.php';
$resultats = [];
printf("Base %s · memory_limit=%s · max_execution_time=%ds · %d page(s) × %d profil(s)\n\n", perf_nom_base(), $memoire, $duree, count($pages), count($profils));

foreach ($pages as $nom => $url) {
    foreach ($profils as $profil => $email) {
        $commande = [$php, '-d', "memory_limit={$memoire}", '-d', "max_execution_time={$duree}", $profiler, $url, $email, '--json'];
        $debut = microtime(true);
        // Sorties dans des fichiers : un tube plein (message d'erreur volumineux) bloquerait le processus enfant
        $fichierOut = tempnam(sys_get_temp_dir(), 'perf');
        $fichierErr = tempnam(sys_get_temp_dir(), 'perf');
        $proc = proc_open($commande, [1 => ['file', $fichierOut, 'w'], 2 => ['file', $fichierErr, 'w']], $tubes, null, getenv() + ['PERF_DB' => perf_nom_base()]);
        $code = proc_close($proc);
        $out = (string) file_get_contents($fichierOut);
        $err = (string) file_get_contents($fichierErr);
        @unlink($fichierOut);
        @unlink($fichierErr);
        $mur = microtime(true) - $debut;

        $ligne = json_decode(trim((string) strrchr("\n" . trim($out), "\n")), true);
        if (! is_array($ligne)) {
            // Plantage du processus : limite mémoire, durée dépassée, erreur fatale
            preg_match('/(Allowed memory size[^\n]*|Maximum execution time[^\n]*|Fatal error[^\n]*)/', $err . $out, $m);
            $ligne = ['url' => $url, 'utilisateur' => $email, 'statut' => 'PLANTE', 'erreur' => trim($m[1] ?? mb_substr(trim($err . $out), -200)), 'code_sortie' => $code];
        }
        $ligne += ['page' => $nom, 'profil' => $profil, 'duree_mur_ms' => round($mur * 1000)];
        $resultats[] = $ligne;

        printf("%-34s %-6s %-7s %7s ms %5s SQL %7s ms SQL %6s Mo %8s Ko %s\n",
            mb_strimwidth($nom, 0, 34), $profil, $ligne['statut'], $ligne['traitement_ms'] ?? '—', $ligne['sql_nombre'] ?? '—',
            $ligne['sql_ms'] ?? '—', $ligne['memoire_pic_mo'] ?? '—', $ligne['taille_ko'] ?? '—', $ligne['erreur'] ? '⚠ ' . mb_strimwidth($ligne['erreur'], 0, 90) : '');
    }
}

echo "\n=== Récapitulatif trié par nombre de requêtes SQL ===\n";
usort($resultats, fn ($a, $b) => ($b['sql_nombre'] ?? PHP_INT_MAX) <=> ($a['sql_nombre'] ?? PHP_INT_MAX));
foreach ($resultats as $r) {
    printf("%5s SQL  %7s ms  %-34s %-6s %s\n", $r['sql_nombre'] ?? '—', $r['traitement_ms'] ?? '—', mb_strimwidth($r['page'], 0, 34), $r['profil'], $r['statut'] === 'PLANTE' ? 'PLANTE' : '');
}

if ($sortie) {
    file_put_contents($sortie, json_encode($resultats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "\nRésultats complets : {$sortie}\n";
}
