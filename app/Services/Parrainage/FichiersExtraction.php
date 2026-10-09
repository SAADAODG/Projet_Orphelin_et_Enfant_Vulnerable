<?php

namespace App\Services\Parrainage;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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

    /** Colonnes de l'onglet « Par établissement » de la liste pour paiement. */
    private const COLONNES_ETABLISSEMENTS = [
        'region' => 'Région', 'province' => 'Province', 'etablissement' => 'Établissement', 'type' => 'Type d’établissement',
        'banque' => 'Banque', 'code_banque' => 'Code banque', 'code_guichet' => 'Code guichet', 'numero_compte' => 'N° de compte',
        'cle_rib' => 'Clé RIB', 'titulaire' => 'Titulaire', 'nombre_oev' => 'Nombre d’OEV', 'montant' => 'Montant total (FCFA)', 'rib' => 'RIB',
    ];

    private const COLONNES_NOMINATIF = [
        'etablissement' => 'Établissement', 'nom' => 'Nom', 'prenom' => 'Prénom', 'sexe' => 'Sexe', 'date_naissance' => 'Date de naissance',
        'classe' => 'Classe ou filière', 'frais_reels' => 'Frais réels (FCFA)', 'montant_retenu' => 'Montant retenu (FCFA)',
    ];

    public function paiementExcel(array $donnees, array $entete, string $nomFichier): StreamedResponse
    {
        $classeur = new Spreadsheet();

        $feuille = $classeur->getActiveSheet()->setTitle('Par établissement');
        $ligne = $this->ecrireEntete($feuille, $entete);
        $debut = $ligne;
        $feuille->fromArray(array_values(self::COLONNES_ETABLISSEMENTS), null, "A{$ligne}");
        foreach (ExtractionsParrainage::lignesAvecTotaux($donnees['etablissements']) as $l) {
            $ligne++;
            if ($l['niveau'] === 'etablissement') {
                $valeurs = collect(self::COLONNES_ETABLISSEMENTS)->keys()
                    ->map(fn ($cle) => $cle === 'rib' ? ($l['rib_manquant'] ? 'RIB manquant' : 'Complet') : $l[$cle])
                    ->all();
                $feuille->fromArray($valeurs, null, "A{$ligne}", true);
                if ($l['rib_manquant']) {
                    $this->colorer($feuille, "M{$ligne}", 'FDE2E1', 'B42318');
                }
            } else {
                $feuille->fromArray([$l['libelle']], null, "A{$ligne}");
                $feuille->setCellValue("K{$ligne}", $l['nombre_oev'])->setCellValue("L{$ligne}", $l['montant']);
                $feuille->getStyle("A{$ligne}:M{$ligne}")->getFont()->setBold(true);
                $this->colorer($feuille, "A{$ligne}:M{$ligne}", $l['niveau'] === 'general' ? 'D9EAD3' : 'F2F4F3');
            }
        }
        $this->finaliserTableau($feuille, $debut, $ligne, 'M', ['L']);

        $nominatif = $classeur->createSheet()->setTitle('Nominatif');
        $ligne = $this->ecrireEntete($nominatif, $entete);
        $debut = $ligne;
        $nominatif->fromArray(array_values(self::COLONNES_NOMINATIF), null, "A{$ligne}");
        foreach ($donnees['nominatif'] as $l) {
            $nominatif->fromArray(array_values(array_intersect_key($l, self::COLONNES_NOMINATIF)), null, 'A' . ++$ligne, true);
        }
        $ligne++;
        $nominatif->setCellValue("A{$ligne}", 'Total général')
            ->setCellValue("G{$ligne}", $donnees['nominatif']->sum('frais_reels'))
            ->setCellValue("H{$ligne}", $donnees['total_montant']);
        $nominatif->getStyle("A{$ligne}:H{$ligne}")->getFont()->setBold(true);
        $this->finaliserTableau($nominatif, $debut, $ligne, 'H', ['G', 'H']);

        $classeur->setActiveSheetIndex(0);

        return $this->telecharger($classeur, $nomFichier);
    }

    public function paiementPdf(array $donnees, array $entete, string $nomFichier): Response
    {
        return Pdf::loadView('pdf.parrainage-paiement', [
            'entete' => $entete,
            'lignes' => ExtractionsParrainage::lignesAvecTotaux($donnees['etablissements']),
            'donnees' => $donnees,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download($nomFichier);
    }

    /** @param  array<string, callable>  $colonnes  libellé => valeur (voir ExtractionsParrainage::colonnesListeParrain) */
    public function listeExcel(Collection $oevs, array $colonnes, array $entete, string $nomFichier): StreamedResponse
    {
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet()->setTitle('OEV proposés');
        $ligne = $this->ecrireEntete($feuille, $entete);
        $debut = $ligne;
        $feuille->fromArray(array_keys($colonnes), null, "A{$ligne}");
        foreach ($oevs as $oev) {
            $feuille->fromArray(array_map(fn (callable $valeur) => $valeur($oev), array_values($colonnes)), null, 'A' . ++$ligne, true);
        }
        $derniere = chr(ord('A') + count($colonnes) - 1);
        $colonneBesoin = chr(ord('A') + array_search('Besoin financier estimé (FCFA)', array_keys($colonnes), true));
        $this->finaliserTableau($feuille, $debut, $ligne, $derniere, [$colonneBesoin]);

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
        foreach (range('A', $derniereColonne) as $colonne) {
            $feuille->getColumnDimension($colonne)->setAutoSize(true);
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
