<?php

/**
 * Temps des recherches libres (« %terme% ») des écrans principaux, sur la base de PERFORMANCE : un terme fréquent
 * et un terme rare, pour deux profils. Chaque mesure dans un processus séparé (profiler.php), meilleure de 2.
 *
 *   php scripts/perf/mesurer-recherche.php
 */

require __DIR__ . '/_amorce.php';

$pdo = perf_pdo();
$session = (int) $pdo->query("select max(id) from sessions_parrainage where etat = 'en_cours'")->fetchColumn();
$rare = substr((string) $pdo->query("select code from oevs where code is not null order by id desc offset 777 limit 1")->fetchColumn(), -8);
$pages = [
    'Liste des OEV' => '/admin/liste-oev?q=%s',
    'Constituer dossier' => '/admin/oevs?etat=tous&q=%s',
    'Filtrage et extraction' => '/admin/filtrage-extraction?q=%s',
    'Sélection : éligibles' => "/admin/parrainage/sessions/{$session}/selection?onglet=eligibles&q=%s",
    'Journal d’audit' => '/admin/journal?q=%s',
];
printf("%-26s %-6s %-12s %10s\n", 'Page', 'Profil', 'Terme', 'ms');
foreach ($pages as $nom => $modele) {
    foreach (['admin@oev.gov.bf' => 'admin', 'dp@oev.gov.bf' => 'dp'] as $email => $profil) {
        foreach (['sank', $rare] as $terme) {
            $meilleur = null;
            foreach ([1, 2] as $_) {
                $sortie = shell_exec(escapeshellarg(PHP_BINARY) . ' -d memory_limit=128M ' . escapeshellarg(__DIR__ . '/profiler.php') . ' '
                    . escapeshellarg(sprintf($modele, rawurlencode($terme))) . ' ' . escapeshellarg($email) . ' --json');
                $r = json_decode(trim((string) strrchr("\n" . trim((string) $sortie), "\n")), true);
                $ms = is_array($r) && $r['statut'] === 200 ? $r['traitement_ms'] : null;
                $meilleur = $ms === null ? $meilleur : min($meilleur ?? PHP_INT_MAX, $ms);
            }
            printf("%-26s %-6s %-12s %10s\n", mb_strimwidth($nom, 0, 26), $profil, $terme, $meilleur ?? '—');
        }
    }
}
