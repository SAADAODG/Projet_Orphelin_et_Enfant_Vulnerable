<?php

namespace App\Services\Parrainage;

use App\Models\Oev;
use App\Models\SuiviParrainage;
use App\Support\Montant;

/**
 * Parcours scolaire et professionnel d'un OEV, année par année, sans rien perdre :
 *  - l'année précédant le premier parrainage (renseignée sur la fiche d'origine) ;
 *  - chaque année parrainée : la situation de la fiche à ce moment-là (conservée par le suivi) et son résultat ;
 *  - l'année en cours (la fiche actuelle).
 * La fiche ne montre que la situation actuelle : l'historique se reconstitue à partir des suivis.
 */
class ParcoursScolaire
{
    /**
     * @return list<array{annee: string, periode: string, situation: string, detail: ?string, frais: ?string,
     *                    resultat: ?string, couleur: ?string, moyenne: ?string, parrainage: ?string, suite: ?string, observation: ?string}>
     */
    public function pour(Oev $oev): array
    {
        $suivis = SuiviParrainage::with('beneficiaire.session')->where('oev_id', $oev->id)->orderBy('annee')->orderBy('id')->get();
        $etapes = [];

        // Année précédant le premier parrainage (ou l'année précédente de la fiche, sans parrainage)
        $origine = $suivis->first()?->fiche_avant ?? FicheApresSuivi::photographier($oev);
        if (filled($origine['classe_precedente'] ?? null) || filled($origine['etablissement_precedent'] ?? null)) {
            $etapes[] = $this->etape(
                $suivis->isNotEmpty() ? self::anneePrecedente($suivis->first()->annee) : 'Année précédente',
                'precedente',
                Oev::toutesLesClasses()[$origine['classe_precedente'] ?? ''] ?? 'Scolarisé',
                $origine['etablissement_precedent'] ?? null,
                null,
                Oev::APPRECIATIONS[$origine['appreciation'] ?? ''] ?? null,
                match ($origine['appreciation'] ?? null) { 'admis' => 'success', 'redouble' => 'warning', 'exclu' => 'danger', default => null },
                self::moyenne($origine['moyenne_annuelle'] ?? null, $origine['classe_precedente'] ?? null),
            );
        }

        // Chaque année parrainée : la situation d'alors et le résultat du suivi
        foreach ($suivis as $suivi) {
            $etat = $suivi->fiche_avant ?? [];
            [$situation, $detail, $frais] = $this->situation($etat);
            $session = $suivi->beneficiaire?->session;
            $etapes[] = $this->etape($suivi->annee, 'parrainee', $situation, $detail, $frais, $suivi->libelleResultat(), $suivi->couleur(),
                self::moyenne($suivi->moyenne, $etat['classe'] ?? null),
                'Parrainé' . ($session ? " · session n°{$session->numero}" : '') . ($suivi->beneficiaire ? ' · ' . Montant::fcfa($suivi->beneficiaire->montant_retenu) : ''),
                trim(implode(' · ', array_filter([
                    $suivi->libelleProchaineAnnee() ? 'Ensuite : ' . $suivi->libelleProchaineAnnee() : null,
                    $suivi->insertion ? SuiviParrainage::INSERTIONS[$suivi->insertion] : null,
                    $suivi->a_reconduire ? 'Recommandé pour un nouveau parrainage' : 'Non recommandé pour un nouveau parrainage',
                ]))) ?: null,
                $suivi->observation,
            );
        }

        // Année en cours : la fiche actuelle
        [$situation, $detail, $frais] = $this->situation(FicheApresSuivi::photographier($oev));
        $etapes[] = $this->etape($suivis->isNotEmpty() ? self::anneeSuivante($suivis->last()->annee) : 'Année en cours', 'actuelle', $situation, $detail, $frais);

        return $etapes;
    }

    /** @return array{0: string, 1: ?string, 2: ?string} situation, établissement ou centre, frais */
    private function situation(array $etat): array
    {
        $enFormation = (bool) ($etat['formation_professionnelle'] ?? false);
        $type = fn (?string $code) => $code ? (Oev::TYPES_ETABLISSEMENT[$code] ?? $code) : null;

        return match (true) {
            ($etat['situation_scolaire'] ?? null) === 'scolarise' => [
                Oev::toutesLesClasses()[$etat['classe'] ?? ''] ?? 'Scolarisé',
                trim(implode(' · ', array_filter([$etat['etablissement_actuel'] ?? null, $type($etat['type_etablissement'] ?? null)]))) ?: null,
                isset($etat['frais_scolarite']) ? 'Scolarité : ' . Montant::fcfa((int) $etat['frais_scolarite']) : null,
            ],
            $enFormation && ($etat['formation_etat'] ?? null) === 'en_cours' => [
                'Formation professionnelle' . (filled($etat['formation_filiere'] ?? null) ? ' : ' . $etat['formation_filiere'] : ''),
                trim(implode(' · ', array_filter([
                    $type($etat['formation_type_centre'] ?? null) ? 'Centre ' . mb_strtolower($type($etat['formation_type_centre'])) : null,
                    filled($etat['formation_duree_mois'] ?? null) ? $etat['formation_duree_mois'] . ' mois' : null,
                ]))) ?: null,
                isset($etat['frais_formation']) ? 'Formation : ' . Montant::fcfa((int) $etat['frais_formation']) : null,
            ],
            $enFormation && ($etat['formation_etat'] ?? null) === 'achevee' => [
                'Formation achevée' . (filled($etat['formation_filiere'] ?? null) ? ' : ' . $etat['formation_filiere'] : ''), null, null,
            ],
            default => [
                Oev::SITUATIONS_SCOLAIRES[$etat['situation_scolaire'] ?? ''] ?? 'Situation non renseignée',
                filled($etat['raison_non_scolarisation_precision'] ?? null)
                    ? $etat['raison_non_scolarisation_precision']
                    : (Oev::RAISONS_NON_SCOLARISATION[$etat['raison_non_scolarisation'] ?? ''] ?? null),
                null,
            ],
        };
    }

    private function etape(string $annee, string $periode, string $situation, ?string $detail = null, ?string $frais = null, ?string $resultat = null,
        ?string $couleur = null, ?string $moyenne = null, ?string $parrainage = null, ?string $suite = null, ?string $observation = null): array
    {
        return compact('annee', 'periode', 'situation', 'detail', 'frais', 'resultat', 'couleur', 'moyenne', 'parrainage', 'suite', 'observation');
    }

    /** « 12.50 » en 5e → « 12,5/20 ». */
    private static function moyenne(mixed $valeur, ?string $classe): ?string
    {
        return $valeur === null || $valeur === '' ? null : str_replace('.', ',', (string) (float) $valeur) . '/' . Oev::baremeMoyenne($classe);
    }

    public static function anneePrecedente(string $annee): string
    {
        [$debut] = array_map('intval', explode('-', $annee));

        return ($debut - 1) . '-' . $debut;
    }

    public static function anneeSuivante(string $annee): string
    {
        [, $fin] = array_map('intval', explode('-', $annee)) + [1 => 0];

        return $fin . '-' . ($fin + 1);
    }
}
