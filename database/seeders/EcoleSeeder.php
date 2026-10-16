<?php

namespace Database\Seeders;

use App\Services\Referentiel\ImportEcoles;
use Illuminate\Database\Seeder;

/**
 * Écoles du Burkina Faso, rattachées à leur commune (Paramètres > Écoles).
 *
 * Source : fichier « structure_ecole » du ministère en charge de l'éducation, réduit dans
 * database/data/ecoles.csv aux colonnes région, province, commune, CEB, école, statut, type.
 * Les lignes dont la commune est introuvable sont ignorées et signalées. Relancer le seeder
 * n'ajoute pas de doublons.
 */
class EcoleSeeder extends Seeder
{
    public function run(): void
    {
        $flux = fopen(database_path('data/ecoles.csv'), 'r');
        fgetcsv($flux); // en-tête
        $lignes = function () use ($flux) {
            while (($ligne = fgetcsv($flux)) !== false) {
                yield $ligne;
            }
        };

        $rapport = (new ImportEcoles)->importer($lignes());
        fclose($flux);

        $this->command?->info("{$rapport['ajoutees']} écoles rattachées.");
        if ($rapport['communes_introuvables']) {
            $this->command?->warn(count($rapport['communes_introuvables']) . ' commune(s) de la source introuvable(s) : ' . implode(', ', $rapport['communes_introuvables']));
        }
    }
}
