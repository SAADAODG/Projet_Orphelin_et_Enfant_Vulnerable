<?php

namespace App\Services\Referentiel;

use App\Models\Commune;
use App\Models\Ecole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import de la structure des écoles (fichier du ministère : région, province, commune, CEB, école,
 * statut, type), utilisé par EcoleSeeder (database/data/ecoles.csv) et par l'import Excel de
 * Paramètres > Écoles.
 *
 * Le rattachement se fait par le nom de la commune (sans accents ni ponctuation), la province ne
 * servant qu'à départager des communes homonymes. Une école déjà connue (même commune, CEB et nom)
 * est mise à jour : relancer l'import n'ajoute pas de doublons.
 */
class ImportEcoles
{
    /** Orthographes de communes différentes entre le fichier des écoles et CommuneSeeder (clés normalisées). */
    private const ALIAS_COMMUNES = [
        'iolonioro' => 'nioronioro', 'tanguendassouri' => 'tanghindassouri', 'ouri' => 'oury', 'bomborokui' => 'bomborokuy',
        'dokui' => 'dokuy', 'bondokui' => 'bondokuy', 'bitou' => 'bittou', 'kominyanga' => 'cominyanga', 'gounguen' => 'gounghin',
        'tensobentenga' => 'tansobentenga', 'sabse' => 'sabce', 'zimtanga' => 'zimtenga', 'zeguedeguen' => 'zeguedeguin',
        'imasgho' => 'imasgo', 'soa' => 'soaw', 'niabouri' => 'niambouri', 'ipelse' => 'ipelce', 'koala' => 'coalla',
        'matiakoali' => 'matiacoali', 'logbou' => 'logobou', 'namouno' => 'namounou', 'morlaba' => 'morolaba',
        'samogogouan' => 'samorogouan', 'bekui' => 'bekuy', 'founzan' => 'fouzan', 'bahn' => 'banh', 'boken' => 'bokin',
        'gomponsom' => 'gompomsom', 'senguenega' => 'seguenega', 'goursi' => 'gourci', 'boudri' => 'boudry',
        'megue' => 'meguet', 'saolgo' => 'salogo', 'toeguen' => 'toeghin', 'ambsouya' => 'absouya', 'dapeolgo' => 'dapelogo',
        'falagountou' => 'falangountou', 'kpuere' => 'kpere',
        'bondoukuy' => 'bondokuy', 'bondigui' => 'gbondjigui', 'tankougounadje' => 'tankougounadie', 'kokologho' => 'kokologo',
        'thyou' => 'thiou', 'cassou' => 'kassou', 'gonboussougou' => 'gomboussougou', 'manni' => 'mani', 'wolokonto' => 'wolonkoto',
        'gourcy' => 'gourci',
    ];

    /** Anciens noms de provinces => nom actuel, pour départager les communes homonymes. */
    private const ALIAS_PROVINCES = [
        'sanmatenga' => 'sandbondtenga',
    ];

    /** cle normalisée de commune => liste de ['id', 'provinces' => clés de province possibles] */
    private array $communes;

    /** cle normalisée (statut / type) => valeur enregistrée */
    private array $statuts;

    private array $types;

    public function __construct()
    {
        $this->communes = $this->indexCommunes();
        $this->statuts = $this->indexListe(Ecole::STATUTS);
        $this->types = $this->indexListe(Ecole::TYPES);
    }

    /**
     * @param  iterable<array>  $lignes  [région, province, commune, CEB, école, statut, type] (sans en-tête)
     * @return array{ajoutees: int, mises_a_jour: int, ignorees: int, communes_introuvables: list<string>}
     */
    public function importer(iterable $lignes): array
    {
        // École connue (clé normalisée) => [id, statut, type]
        $existantes = [];
        foreach (DB::table('ecoles')->select(['id', 'commune_id', 'ceb', 'nom', 'statut', 'type'])->cursor() as $ecole) {
            $existantes[$this->cleEcole($ecole->commune_id, $ecole->ceb, $ecole->nom)] = [$ecole->id, $ecole->statut, $ecole->type];
        }

        $rapport = ['ajoutees' => 0, 'mises_a_jour' => 0, 'ignorees' => 0, 'communes_introuvables' => []];
        $introuvables = [];
        $lot = [];
        $vues = [];
        $maintenant = now();
        // Écoles insérées par lots au fil de la lecture (un fichier national compte plus de 20 000 lignes)
        $inserer = function () use (&$lot): void {
            if ($lot) {
                DB::table('ecoles')->insert($lot);
                $lot = [];
            }
        };

        DB::transaction(function () use ($lignes, $existantes, &$vues, &$rapport, &$introuvables, &$lot, $inserer, $maintenant) {
            foreach ($lignes as $cellules) {
                [, $province, $commune, $ceb, $nom, $statut, $type] = array_pad(array_map(fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v)), array_values($cellules)), 7, '');
                if ($nom === '') {
                    $rapport['ignorees']++;
                    continue;
                }
                $communeId = $this->trouverCommune($province, $commune);
                if (! $communeId) {
                    $introuvables["{$commune} ({$province})"] = true;
                    $rapport['ignorees']++;
                    continue;
                }

                $ceb = $ceb !== '' ? $ceb : null;
                $statut = $this->statuts[$this->cle($statut)] ?? null;
                $type = $type === '' ? null : ($this->types[$this->cle($type)] ?? 'autre');
                $cle = $this->cleEcole($communeId, $ceb, $nom);

                // Ligne répétée dans le fichier : la première est retenue
                if (isset($vues[$cle])) {
                    continue;
                }
                $vues[$cle] = true;

                if (isset($existantes[$cle])) {
                    [$id, $statutConnu, $typeConnu] = $existantes[$cle];
                    if ($statutConnu !== $statut || $typeConnu !== $type) {
                        DB::table('ecoles')->where('id', $id)->update(['statut' => $statut, 'type' => $type, 'updated_at' => $maintenant]);
                        $rapport['mises_a_jour']++;
                    }
                    continue;
                }

                $lot[] = ['commune_id' => $communeId, 'ceb' => $ceb, 'nom' => $nom, 'statut' => $statut, 'type' => $type, 'created_at' => $maintenant, 'updated_at' => $maintenant];
                $rapport['ajoutees']++;
                if (count($lot) >= 500) {
                    $inserer();
                }
            }
            $inserer();
        });

        $rapport['communes_introuvables'] = array_keys($introuvables);

        return $rapport;
    }

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

    private function trouverCommune(string $province, string $commune): ?int
    {
        $cle = $this->cle($commune);
        $candidats = $this->communes[self::ALIAS_COMMUNES[$cle] ?? $cle] ?? [];

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

    /** Valeurs reconnues par leur code ou leur libellé (« FRANCO-ARABE », « Privé »…). */
    private function indexListe(array $liste): array
    {
        $index = [];
        foreach ($liste as $code => $libelle) {
            $index[$this->cle($code)] = $code;
            $index[$this->cle($libelle)] = $code;
        }

        return $index;
    }

    private function cleEcole(int $communeId, ?string $ceb, string $nom): string
    {
        return $communeId . '|' . $this->cle($ceb) . '|' . $this->cle($nom);
    }

    private function cle(?string $nom): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $nom)));
    }
}
