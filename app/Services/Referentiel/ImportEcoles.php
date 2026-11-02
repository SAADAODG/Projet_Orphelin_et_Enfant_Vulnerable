<?php

namespace App\Services\Referentiel;

use App\Models\Commune;
use App\Models\Ecole;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import de la structure des écoles (fichier du ministère : région, province, commune, CEB, école,
 * statut, type), utilisé par EcoleSeeder (database/data/ecoles.csv) et par l'import Excel de
 * Paramètres > Écoles.
 *
 * Le rattachement se fait par le nom de la commune (sans accents ni ponctuation), la province ne
 * servant qu'à départager des communes homonymes. Toutes les lignes du fichier sont importées, y compris
 * les écoles de même nom dans la même CEB (le fichier en compte, parfois de statut ou de type différents).
 * La n-ième ligne « commune + CEB + nom » du fichier correspond à la n-ième école enregistrée de même
 * commune, CEB et nom : elle est mise à jour (statut, type) et relancer l'import n'ajoute pas de doublons.
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

    /** Écoles insérées par lots au fil de la lecture (un fichier national compte plus de 20 000 lignes). */
    private const TAILLE_LOT = 500;

    /* État de l'import en cours */

    /** Écoles connues (clé normalisée) => liste de [id, statut, type], dans l'ordre d'enregistrement */
    private array $existantes = [];

    /** Clé normalisée => nombre de lignes déjà lues avec cette clé */
    private array $occurrences = [];

    private array $lot = [];

    /** « commune (province) » => true */
    private array $introuvables = [];

    private CarbonInterface $maintenant;

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
        $this->existantes = $this->ecolesExistantes();
        $this->occurrences = [];
        $this->lot = [];
        $this->introuvables = [];
        $this->maintenant = now();
        $rapport = ['ajoutees' => 0, 'mises_a_jour' => 0, 'ignorees' => 0, 'communes_introuvables' => []];

        DB::transaction(function () use ($lignes, &$rapport) {
            foreach ($lignes as $cellules) {
                if ($resultat = $this->traiterLigne($cellules)) {
                    $rapport[$resultat]++;
                }
            }
            $this->insererLot();
        });

        $rapport['communes_introuvables'] = array_keys($this->introuvables);

        return $rapport;
    }

    /** Écoles connues (clé normalisée) => liste de [id, statut, type], dans l'ordre d'enregistrement. */
    private function ecolesExistantes(): array
    {
        $existantes = [];
        foreach (DB::table('ecoles')->select(['id', 'commune_id', 'ceb', 'nom', 'statut', 'type'])->orderBy('id')->cursor() as $ecole) {
            $existantes[$this->cleEcole($ecole->commune_id, $ecole->ceb, $ecole->nom)][] = [$ecole->id, $ecole->statut, $ecole->type];
        }

        return $existantes;
    }

    /** @return string|null compteur du rapport à augmenter (ignorees, mises_a_jour, ajoutees), null si l'école est déjà à jour */
    private function traiterLigne(array $cellules): ?string
    {
        $ecole = $this->lireLigne($cellules);
        if ($ecole === null) {
            return 'ignorees';
        }

        $cle = $this->cleEcole($ecole['commune_id'], $ecole['ceb'], $ecole['nom']);
        // Rang de cette école parmi les lignes de même commune, CEB et nom
        $rang = $this->occurrences[$cle] = ($this->occurrences[$cle] ?? -1) + 1;
        $existante = $this->existantes[$cle][$rang] ?? null;

        return $existante ? $this->mettreAJour($existante, $ecole) : $this->ajouter($ecole);
    }

    /** École lue sur la ligne, avec sa commune ; null si la ligne est ignorée (sans nom, ou commune introuvable). */
    private function lireLigne(array $cellules): ?array
    {
        [, $province, $commune, $ceb, $nom, $statut, $type] = array_pad(array_map(fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v)), array_values($cellules)), 7, '');
        $communeId = $nom === '' ? null : $this->trouverCommune($province, $commune);
        if (! $communeId) {
            if ($nom !== '') {
                $this->introuvables["{$commune} ({$province})"] = true;
            }

            return null;
        }

        return [
            'commune_id' => $communeId,
            'ceb' => $ceb !== '' ? $ceb : null,
            'nom' => $nom,
            'statut' => $this->statuts[$this->cle($statut)] ?? null,
            'type' => $type === '' ? null : ($this->types[$this->cle($type)] ?? 'autre'),
        ];
    }

    /** Statut ou type changé : l'école enregistrée est mise à jour. */
    private function mettreAJour(array $existante, array $ecole): ?string
    {
        [$id, $statutConnu, $typeConnu] = $existante;
        if ($statutConnu === $ecole['statut'] && $typeConnu === $ecole['type']) {
            return null;
        }
        DB::table('ecoles')->where('id', $id)->update(['statut' => $ecole['statut'], 'type' => $ecole['type'], 'updated_at' => $this->maintenant]);

        return 'mises_a_jour';
    }

    private function ajouter(array $ecole): string
    {
        $this->lot[] = $ecole + ['created_at' => $this->maintenant, 'updated_at' => $this->maintenant];
        if (count($this->lot) >= self::TAILLE_LOT) {
            $this->insererLot();
        }

        return 'ajoutees';
    }

    private function insererLot(): void
    {
        if ($this->lot) {
            DB::table('ecoles')->insert($this->lot);
            $this->lot = [];
        }
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
