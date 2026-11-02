<?php

namespace App\Services\Parrainage;

use App\Models\Oev;
use App\Models\SuiviParrainage;
use App\Support\Montant;
use Illuminate\Support\Collection;

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
        if ($origine = $this->etapeOrigine($oev, $suivis)) {
            $etapes[] = $origine;
        }
        foreach ($suivis as $suivi) {
            $etapes[] = $this->etapeParrainee($suivi);
        }
        $etapes[] = $this->etapeActuelle($oev, $suivis);

        return $etapes;
    }

    /** Année précédant le premier parrainage (ou l'année précédente de la fiche, sans parrainage) ; null si elle n'est pas renseignée. */
    private function etapeOrigine(Oev $oev, Collection $suivis): ?array
    {
        $origine = $suivis->first()?->fiche_avant ?? FicheApresSuivi::photographier($oev);
        if (blank($origine['classe_precedente'] ?? null) && blank($origine['etablissement_precedent'] ?? null)) {
            return null;
        }

        return $this->etape([
            'annee' => $suivis->isNotEmpty() ? self::anneePrecedente($suivis->first()->annee) : 'Année précédente',
            'periode' => 'precedente',
            'situation' => Oev::toutesLesClasses()[$origine['classe_precedente'] ?? ''] ?? 'Scolarisé',
            'detail' => $origine['etablissement_precedent'] ?? null,
            'resultat' => Oev::APPRECIATIONS[$origine['appreciation'] ?? ''] ?? null,
            'couleur' => match ($origine['appreciation'] ?? null) { 'admis' => 'success', 'redouble' => 'warning', 'exclu' => 'danger', default => null },
            'moyenne' => self::moyenne($origine['moyenne_annuelle'] ?? null, $origine['classe_precedente'] ?? null),
        ]);
    }

    /** Une année parrainée : la situation d'alors et le résultat du suivi. */
    private function etapeParrainee(SuiviParrainage $suivi): array
    {
        $etat = $suivi->fiche_avant ?? [];
        [$situation, $detail, $frais] = $this->situation($etat);
        $session = $suivi->beneficiaire?->session;

        return $this->etape([
            'annee' => $suivi->annee,
            'periode' => 'parrainee',
            'situation' => $situation,
            'detail' => $detail,
            'frais' => $frais,
            'resultat' => $suivi->libelleResultat(),
            'couleur' => $suivi->couleur(),
            'moyenne' => self::moyenne($suivi->moyenne, $etat['classe'] ?? null),
            'parrainage' => 'Parrainé' . ($session ? " · session n°{$session->numero}" : '') . ($suivi->beneficiaire ? ' · ' . Montant::fcfa($suivi->beneficiaire->montant_retenu) : ''),
            'suite' => self::joindre([
                $suivi->libelleProchaineAnnee() ? 'Ensuite : ' . $suivi->libelleProchaineAnnee() : null,
                $suivi->insertion ? SuiviParrainage::INSERTIONS[$suivi->insertion] : null,
                $suivi->a_reconduire ? 'Recommandé pour un nouveau parrainage' : 'Non recommandé pour un nouveau parrainage',
            ]),
            'observation' => $suivi->observation,
        ]);
    }

    /** Année en cours : la fiche actuelle. */
    private function etapeActuelle(Oev $oev, Collection $suivis): array
    {
        [$situation, $detail, $frais] = $this->situation(FicheApresSuivi::photographier($oev));

        return $this->etape([
            'annee' => $suivis->isNotEmpty() ? self::anneeSuivante($suivis->last()->annee) : 'Année en cours',
            'periode' => 'actuelle',
            'situation' => $situation,
            'detail' => $detail,
            'frais' => $frais,
        ]);
    }

    /** @return array{0: string, 1: ?string, 2: ?string} situation, établissement ou centre, frais */
    private function situation(array $etat): array
    {
        $enFormation = (bool) ($etat['formation_professionnelle'] ?? false);
        $type = fn (?string $code) => $code ? (Oev::TYPES_ETABLISSEMENT[$code] ?? $code) : null;

        return match (true) {
            ($etat['situation_scolaire'] ?? null) === 'scolarise' => [
                Oev::toutesLesClasses()[$etat['classe'] ?? ''] ?? 'Scolarisé',
                self::joindre([$etat['etablissement_actuel'] ?? null, $type($etat['type_etablissement'] ?? null)]),
                isset($etat['frais_scolarite']) ? 'Scolarité : ' . Montant::fcfa((int) $etat['frais_scolarite']) : null,
            ],
            $enFormation && ($etat['formation_etat'] ?? null) === 'en_cours' => [
                'Formation professionnelle' . (filled($etat['formation_filiere'] ?? null) ? ' : ' . $etat['formation_filiere'] : ''),
                self::joindre([
                    $type($etat['formation_type_centre'] ?? null) ? 'Centre ' . mb_strtolower($type($etat['formation_type_centre'])) : null,
                    filled($etat['formation_duree_mois'] ?? null) ? $etat['formation_duree_mois'] . ' mois' : null,
                ]),
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

    /** Champs d'une étape du parcours (voir pour()) : ceux qui ne sont pas fournis valent null. */
    private const ETAPE = [
        'annee' => null, 'periode' => null, 'situation' => null, 'detail' => null, 'frais' => null, 'resultat' => null,
        'couleur' => null, 'moyenne' => null, 'parrainage' => null, 'suite' => null, 'observation' => null,
    ];

    /** @param  array<string, ?string>  $valeurs  annee, periode et situation au moins */
    private function etape(array $valeurs): array
    {
        return array_merge(self::ETAPE, $valeurs);
    }

    /** Parties renseignées séparées par « · », null si aucune. */
    private static function joindre(array $parties): ?string
    {
        return trim(implode(' · ', array_filter($parties))) ?: null;
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
