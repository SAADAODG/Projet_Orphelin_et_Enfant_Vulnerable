<?php

namespace Database\Seeders;

use App\Models\Commune;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Villages et villes du Burkina Faso, rattachés à leur commune.
 *
 * Source : BNDT / IGB — « villes et villages administratifs avec P-codes », diffusé par
 * OCHA sur HDX (jeu « Burkina Faso: Settlements »), réduit dans database/data/villages.csv
 * aux colonnes région, province, commune, village (noms administratifs).
 *
 * Le fichier utilise l'ancien découpage (13 régions, anciens noms de provinces) : le
 * rattachement se fait donc par le nom de la commune, la province ne servant qu'à
 * départager des communes homonymes. Les lignes dont la commune est introuvable sont
 * ignorées et comptées. Relancer le seeder n'ajoute pas de doublons.
 */
class VillageSeeder extends Seeder
{
    /** Orthographes de communes différentes entre la source et CommuneSeeder (clés normalisées). */
    private const ALIAS_COMMUNES = [
        'tanguendassouri' => 'tanghindassouri', 'ouri' => 'oury', 'bomborokui' => 'bomborokuy', 'dokui' => 'dokuy',
        'bondokui' => 'bondokuy', 'bitou' => 'bittou', 'kominyanga' => 'cominyanga', 'gounguen' => 'gounghin',
        'tensobentenga' => 'tansobentenga', 'sabse' => 'sabce', 'zimtanga' => 'zimtenga', 'zeguedeguen' => 'zeguedeguin',
        'imasgho' => 'imasgo', 'soa' => 'soaw', 'niabouri' => 'niambouri', 'ipelse' => 'ipelce', 'koala' => 'coalla',
        'matiakoali' => 'matiacoali', 'logbou' => 'logobou', 'namouno' => 'namounou', 'morlaba' => 'morolaba',
        'samogogouan' => 'samorogouan', 'bekui' => 'bekuy', 'founzan' => 'fouzan', 'bahn' => 'banh', 'boken' => 'bokin',
        'gomponsom' => 'gompomsom', 'senguenega' => 'seguenega', 'goursi' => 'gourci', 'boudri' => 'boudry',
        'megue' => 'meguet', 'saolgo' => 'salogo', 'toeguen' => 'toeghin', 'ambsouya' => 'absouya', 'dapeolgo' => 'dapelogo',
        'falagountou' => 'falangountou', 'iolonioro' => 'nioronioro', 'kpuere' => 'kpere',
    ];

    /** Anciens noms de provinces de la source => nom actuel, pour départager les communes homonymes. */
    private const ALIAS_PROVINCES = [
        'sanmatenga' => 'sandbondtenga',
    ];

    public function run(): void
    {
        $fichier = database_path('data/villages.csv');
        $communes = $this->indexCommunes();

        $aInserer = [];
        $ignores = [];
        $maintenant = now();

        $flux = fopen($fichier, 'r');
        fgetcsv($flux); // en-tête
        while (($ligne = fgetcsv($flux)) !== false) {
            [, $province, $commune, $village] = $ligne;
            $communeId = $this->trouverCommune($communes, $province, $commune);

            if (! $communeId) {
                $ignores["{$commune} ({$province})"] = true;
                continue;
            }
            $aInserer[$communeId . '|' . Str::lower($village)] = [
                'commune_id' => $communeId, 'nom' => $village, 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ];
        }
        fclose($flux);

        foreach (array_chunk(array_values($aInserer), 500) as $lot) {
            DB::table('villages')->insertOrIgnore($lot);
        }

        $this->command?->info(count($aInserer) . ' villages rattachés.');
        if ($ignores) {
            $this->command?->warn(count($ignores) . ' commune(s) de la source introuvable(s) : ' . implode(', ', array_keys($ignores)));
        }
    }

    /** cle normalisée de commune => liste de [id, clés de province possibles] */
    private function indexCommunes(): array
    {
        $index = [];
        foreach (Commune::with('province')->get() as $commune) {
            $provinces = [$this->cle($commune->province->nom)];
            // « Koosin (Kossi) » répond aussi à l'ancienne appellation « Kossi »
            if (preg_match('/\(([^)]+)\)/', $commune->province->nom, $m)) {
                $provinces[] = $this->cle($m[1]);
            }
            $index[$this->cle($commune->nom)][] = ['id' => $commune->id, 'provinces' => $provinces];
        }

        return $index;
    }

    private function trouverCommune(array $communes, string $province, string $commune): ?int
    {
        $cle = $this->cle($commune);
        $candidats = $communes[self::ALIAS_COMMUNES[$cle] ?? $cle] ?? [];

        if (count($candidats) === 1) {
            return $candidats[0]['id'];
        }
        $cleProvince = $this->cle($province);
        $cleProvince = self::ALIAS_PROVINCES[$cleProvince] ?? $cleProvince;
        foreach ($candidats as $candidat) {
            if (in_array($cleProvince, $candidat['provinces'], true)) {
                return $candidat['id'];
            }
        }

        return null;
    }

    private function cle(?string $nom): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $nom)));
    }
}
