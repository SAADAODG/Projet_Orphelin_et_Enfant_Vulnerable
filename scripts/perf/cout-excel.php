<?php

/**
 * Coût d'un export Excel (PhpSpreadsheet) par ligne : mémoire et durée pour N lignes × C colonnes, avec ou
 * sans largeur automatique des colonnes. Sert à fixer les limites d'export (aucune base utilisée).
 *
 *   php -d memory_limit=-1 scripts/perf/cout-excel.php [colonnes] [lignes...]
 */

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$colonnes = (int) ($argv[1] ?? 12);
$tailles = array_map('intval', array_slice($argv, 2)) ?: [1000, 5000, 10000];

foreach ([true, false] as $largeurAuto) {
    foreach ($tailles as $n) {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $avant = memory_get_usage();
        $debut = microtime(true);
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet();
        for ($l = 1; $l <= $n; $l++) {
            $ligne = [];
            for ($c = 0; $c < $colonnes; $c++) {
                $ligne[] = $c % 3 === 0 ? 15000 + $l : 'Valeur texte ' . $l . '-' . $c;
            }
            $feuille->fromArray($ligne, null, 'A' . $l, true);
        }
        if ($largeurAuto) {
            for ($i = 1; $i <= $colonnes; $i++) {
                $feuille->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }
        }
        $fichier = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($classeur))->save($fichier);
        $pic = memory_get_peak_usage() - $avant;
        printf("%s %6d lignes × %2d col. : %6.1f Mo (%5.1f Ko/ligne) %6.1f s  fichier %6.0f Ko\n",
            $largeurAuto ? 'largeur auto ' : 'largeur fixe ', $n, $colonnes, $pic / 1048576, $pic / $n / 1024, microtime(true) - $debut, filesize($fichier) / 1024);
        $classeur->disconnectWorksheets();
        unset($classeur, $feuille);
        @unlink($fichier);
    }
}
