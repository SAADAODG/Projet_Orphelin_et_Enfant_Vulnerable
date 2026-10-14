<?php

namespace App\Services\Parrainage;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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
     * Liste pour paiement en Excel : feuille « Par établissement » (sous-totaux par province et région, total
     * général, « RIB manquant » en évidence) et feuille « Nominatif », avec les colonnes choisies.
     *
     * @param  array<string, string>  $colonnesEtablissement  clé => libellé (voir ExtractionsParrainage::COLONNES_ETABLISSEMENT)
     * @param  array<string, string>  $colonnesNominatif  clé => libellé (voir ExtractionsParrainage::COLONNES_NOMINATIF)
     */
    public function paiementExcel(array $donnees, array $entete, string $nomFichier, array $colonnesEtablissement, array $colonnesNominatif): StreamedResponse
    {
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
        return Pdf::loadView('pdf.parrainage-paiement', [
            'entete' => $entete,
            'lignes' => ExtractionsParrainage::lignesAvecTotaux($donnees['etablissements']),
            'donnees' => $donnees,
            'colonnesEtablissement' => $colonnesEtablissement,
            'colonnesNominatif' => $colonnesNominatif,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download($nomFichier);
    }

    /** @param  array<string, callable>  $colonnes  libellé => valeur (voir ExtractionsParrainage::colonnesListeParrain) */
    public function listeExcel(Collection $oevs, array $colonnes, array $entete, string $nomFichier, string $feuilleTitre = 'OEV proposés'): StreamedResponse
    {
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

    public function listePdf(Collection $oevs, array $colonnes, array $entete, string $nomFichier): Response
    {
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
        for ($i = 1; $i <= Coordinate::columnIndexFromString($derniereColonne); $i++) {
            $feuille->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
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
