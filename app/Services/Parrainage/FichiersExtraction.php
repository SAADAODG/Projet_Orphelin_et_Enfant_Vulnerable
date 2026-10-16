<?php

namespace App\Services\Parrainage;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mise en forme des extractions du parrainage en Excel (.xlsx) et en PDF.
 * $entete : ['titre' => string, 'lignes' => string[]] (session, date et auteur de l'extraction, filtres).
 */
class FichiersExtraction
{
    private const FORMAT_MONTANT = '#,##0';
    private const COULEUR_ENTETE = '0F7A43';

    /**
     * Limites des exports, mesurées (scripts/perf/cout-excel.php) : PhpSpreadsheet garde tout le classeur en
     * mémoire, environ 0,7 Ko par cellule. 250 000 cellules ≈ 190 Mo, pour 512 Mo accordés à ces requêtes
     * (marge × 2). Au-delà, l'utilisateur affine ses filtres ou coche moins de données.
     */
    public const CELLULES_MAX = 250_000;
    public const LIGNES_MAX = 50_000;
    public const MEMOIRE_EXPORT = '512M';
    public const DUREE_EXPORT = 300;

    /** Au-delà, un PDF devient illisible et lourd à produire (dompdf) : l'export Excel est proposé. */
    public const PDF_LIGNES_MAX = 1000;

    /**
     * Lignes d'une requête dans l'ordre de son tri, lues par paquets pour un export : les identifiants d'abord
     * (une requête triée), puis les fiches par paquets de clés, avec leurs relations. Contrairement à une
     * lecture par pages (OFFSET), le tri n'est fait qu'une fois, quelle que soit la taille de l'export.
     *
     * @param  array<int, string>  $relations
     */
    public static function dansLOrdre(Builder $requete, array $relations = [], int $paquet = 500): LazyCollection
    {
        $modele = $requete->getModel();
        $requete = clone $requete;

        // Rien n'est lu avant le parcours (un export refusé ne coûte rien)
        return LazyCollection::make(function () use ($modele, $requete, $relations, $paquet) {
            $ids = $requete->toBase()->pluck($modele->getQualifiedKeyName())->all();
            foreach (array_chunk($ids, $paquet) as $morceau) {
                $fiches = $modele->newQuery()->with($relations)->whereKey($morceau)->get()->keyBy($modele->getKeyName());
                foreach ($morceau as $id) {
                    if (isset($fiches[$id])) {
                        yield $fiches[$id];
                    }
                }
            }
        });
    }

    /** Largeur automatique des colonnes (coûteuse : chaque cellule est mesurée) jusqu'à ce nombre de lignes. */
    private const LARGEUR_AUTO_MAX = 2000;

    /** Nombre de lignes au plus d'un export Excel de $colonnes colonnes. */
    public static function lignesExcelMax(int $colonnes): int
    {
        return min(self::LIGNES_MAX, intdiv(self::CELLULES_MAX, max(1, $colonnes)));
    }

    /**
     * Mémoire et durée relevées pour la seule requête d'export en cours (la configuration de PHP n'est pas
     * modifiée) ; jamais abaissées.
     */
    public static function prevoirGrosFichier(): void
    {
        $actuelle = ini_get('memory_limit');
        $octets = fn (string $valeur) => $valeur === '-1' ? PHP_INT_MAX : (int) $valeur * match (strtoupper(substr($valeur, -1))) {
            'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1,
        };
        if ($actuelle !== false && $octets($actuelle) < $octets(self::MEMOIRE_EXPORT)) {
            ini_set('memory_limit', self::MEMOIRE_EXPORT);
        }
        if ((int) ini_get('max_execution_time') !== 0 && (int) ini_get('max_execution_time') < self::DUREE_EXPORT) {
            set_time_limit(self::DUREE_EXPORT);
        }
        // Ramasse-miettes des cycles suspendu : il reparcourt sans cesse les centaines de milliers de cellules du
        // classeur (mesuré : 54 s → 13 s pour 42 000 lignes, même mémoire). Rétabli par PHP en fin de requête.
        ini_set('zend.enable_gc', '0');
    }

    /**
     * Liste pour paiement en Excel : feuille « Par établissement » (sous-totaux par province et région, total
     * général, « RIB manquant » en évidence) et feuille « Nominatif », avec les colonnes choisies.
     *
     * @param  array<string, string>  $colonnesEtablissement  clé => libellé (voir ExtractionsParrainage::COLONNES_ETABLISSEMENT)
     * @param  array<string, string>  $colonnesNominatif  clé => libellé (voir ExtractionsParrainage::COLONNES_NOMINATIF)
     */
    public function paiementExcel(array $donnees, array $entete, string $nomFichier, array $colonnesEtablissement, array $colonnesNominatif): StreamedResponse
    {
        self::prevoirGrosFichier();
        $classeur = new Spreadsheet();
        $lettre = fn (array $colonnes, string $cle) => Coordinate::stringFromColumnIndex(array_search($cle, array_keys($colonnes), true) + 1);

        $feuille = $classeur->getActiveSheet()->setTitle('Par établissement');
        $ligne = $this->ecrireEntete($feuille, $entete);
        $debut = $ligne;
        $derniere = Coordinate::stringFromColumnIndex(count($colonnesEtablissement));
        $feuille->fromArray(array_values($colonnesEtablissement), null, "A{$ligne}");
        foreach (ExtractionsParrainage::lignesAvecTotaux($donnees['etablissements']) as $l) {
            $ligne++;
            if ($l['niveau'] === 'etablissement') {
                $valeurs = array_map(fn ($cle) => $cle === 'rib' ? ($l['rib_manquant'] ? 'RIB manquant' : 'Complet') : $l[$cle], array_keys($colonnesEtablissement));
                $feuille->fromArray($valeurs, null, "A{$ligne}", true);
                if ($l['rib_manquant']) {
                    $cellule = isset($colonnesEtablissement['rib']) ? $lettre($colonnesEtablissement, 'rib') : 'A';
                    $this->colorer($feuille, "{$cellule}{$ligne}", 'FDE2E1', 'B42318');
                }
            } else {
                $feuille->setCellValue("A{$ligne}", $l['libelle'])
                    ->setCellValue($lettre($colonnesEtablissement, 'nombre_oev') . $ligne, $l['nombre_oev'])
                    ->setCellValue($lettre($colonnesEtablissement, 'montant') . $ligne, $l['montant']);
                $feuille->getStyle("A{$ligne}:{$derniere}{$ligne}")->getFont()->setBold(true);
                $this->colorer($feuille, "A{$ligne}:{$derniere}{$ligne}", $l['niveau'] === 'general' ? 'D9EAD3' : 'F2F4F3');
            }
        }
        $this->finaliserTableau($feuille, $debut, $ligne, $derniere, [$lettre($colonnesEtablissement, 'montant')]);

        $nominatif = $classeur->createSheet()->setTitle('Nominatif');
        $ligne = $this->ecrireEntete($nominatif, $entete);
        $debut = $ligne;
        $derniere = Coordinate::stringFromColumnIndex(count($colonnesNominatif));
        $nominatif->fromArray(array_values($colonnesNominatif), null, "A{$ligne}");
        foreach ($donnees['nominatif'] as $l) {
            $nominatif->fromArray(array_map(fn ($cle) => $l[$cle], array_keys($colonnesNominatif)), null, 'A' . ++$ligne, true);
        }
        $ligne++;
        $nominatif->setCellValue("A{$ligne}", 'Total général (' . $donnees['total_oev'] . ' OEV)')
            ->setCellValue($lettre($colonnesNominatif, 'montant_retenu') . $ligne, $donnees['total_montant']);
        if (isset($colonnesNominatif['frais_reels'])) {
            $nominatif->setCellValue($lettre($colonnesNominatif, 'frais_reels') . $ligne, $donnees['nominatif']->sum('frais_reels'));
        }
        $nominatif->getStyle("A{$ligne}:{$derniere}{$ligne}")->getFont()->setBold(true);
        $montants = array_map(fn ($cle) => $lettre($colonnesNominatif, $cle), array_values(array_intersect(['frais_reels', 'montant_retenu'], array_keys($colonnesNominatif))));
        $this->finaliserTableau($nominatif, $debut, $ligne, $derniere, $montants);

        $classeur->setActiveSheetIndex(0);

        return $this->telecharger($classeur, $nomFichier);
    }

    public function paiementPdf(array $donnees, array $entete, string $nomFichier, array $colonnesEtablissement, array $colonnesNominatif): Response
    {
        self::prevoirGrosFichier();
        return Pdf::loadView('pdf.parrainage-paiement', [
            'entete' => $entete,
            'lignes' => ExtractionsParrainage::lignesAvecTotaux($donnees['etablissements']),
            'donnees' => $donnees,
            'colonnesEtablissement' => $colonnesEtablissement,
            'colonnesNominatif' => $colonnesNominatif,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download($nomFichier);
    }

    /**
     * @param  iterable  $oevs  lignes (collection, ou LazyCollection parcourue par paquets)
     * @param  array<string, callable>  $colonnes  libellé => valeur (voir ExtractionsParrainage::colonnesListeParrain)
     */
    public function listeExcel(iterable $oevs, array $colonnes, array $entete, string $nomFichier, string $feuilleTitre = 'OEV proposés'): StreamedResponse
    {
        self::prevoirGrosFichier();
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet()->setTitle($feuilleTitre);
        $ligne = $this->ecrireEntete($feuille, $entete);
        $debut = $ligne;
        $feuille->fromArray(array_keys($colonnes), null, "A{$ligne}");
        foreach ($oevs as $oev) {
            $feuille->fromArray(array_map(fn (callable $valeur) => $valeur($oev), array_values($colonnes)), null, 'A' . ++$ligne, true);
        }
        $derniere = Coordinate::stringFromColumnIndex(count($colonnes));
        // Colonnes de montants (libellé « … (FCFA) ») : séparateur de milliers
        $montants = collect(array_keys($colonnes))
            ->filter(fn (string $libelle) => str_contains($libelle, 'FCFA'))
            ->keys()
            ->map(fn (int $index) => Coordinate::stringFromColumnIndex($index + 1))
            ->all();
        $this->finaliserTableau($feuille, $debut, $ligne, $derniere, $montants);

        return $this->telecharger($classeur, $nomFichier);
    }

    public function listePdf(iterable $oevs, array $colonnes, array $entete, string $nomFichier): Response
    {
        self::prevoirGrosFichier();
        return Pdf::loadView('pdf.parrainage-liste', ['entete' => $entete, 'oevs' => $oevs, 'colonnes' => $colonnes])
            ->setPaper('a4', count($colonnes) > 8 ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->download($nomFichier);
    }

    /** Titre et lignes d'en-tête ; renvoie le numéro de la ligne où commence le tableau. */
    private function ecrireEntete(Worksheet $feuille, array $entete): int
    {
        $feuille->setCellValue('A1', $entete['titre']);
        $feuille->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        foreach ($entete['lignes'] as $i => $texte) {
            $feuille->setCellValue('A' . ($i + 2), $texte);
        }

        return count($entete['lignes']) + 3;
    }

    private function finaliserTableau(Worksheet $feuille, int $debut, int $fin, string $derniereColonne, array $colonnesMontant): void
    {
        $feuille->getStyle("A{$debut}:{$derniereColonne}{$debut}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $this->colorer($feuille, "A{$debut}:{$derniereColonne}{$debut}", self::COULEUR_ENTETE);
        foreach ($colonnesMontant as $colonne) {
            $feuille->getStyle("{$colonne}" . ($debut + 1) . ":{$colonne}{$fin}")->getNumberFormat()->setFormatCode(self::FORMAT_MONTANT);
        }
        // Grand tableau : largeur d'après l'en-tête (la mesure de chaque cellule doublerait la durée)
        $largeurAuto = $fin - $debut <= self::LARGEUR_AUTO_MAX;
        for ($i = 1; $i <= Coordinate::columnIndexFromString($derniereColonne); $i++) {
            $colonne = Coordinate::stringFromColumnIndex($i);
            $largeurAuto
                ? $feuille->getColumnDimension($colonne)->setAutoSize(true)
                : $feuille->getColumnDimension($colonne)->setWidth(max(12, min(40, mb_strlen((string) $feuille->getCell($colonne . $debut)->getValue()) + 4)));
        }
        $feuille->freezePane('A' . ($debut + 1));
    }

    private function colorer(Worksheet $feuille, string $plage, string $fond, ?string $texte = null): void
    {
        $style = $feuille->getStyle($plage);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fond);
        if ($texte) {
            $style->getFont()->setBold(true)->getColor()->setRGB($texte);
        }
    }

    private function telecharger(Spreadsheet $classeur, string $nomFichier): StreamedResponse
    {
        return response()->streamDownload(function () use ($classeur) {
            (new Xlsx($classeur))->save('php://output');
        }, $nomFichier, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
