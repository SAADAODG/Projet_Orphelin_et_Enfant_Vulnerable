<?php

namespace App\Services\Parrainage;

use App\Models\Oev;
use App\Models\SessionParrainage;
use App\Models\SuiviParrainage;

/**
 * Mise à jour de la fiche de l'OEV après son suivi de fin d'année : la scolarité ou la formation
 * de l'année suivante devient la situation de la fiche (et donc celle prise en compte par la sélection
 * des sessions suivantes). Les règles du formulaire de constitution sont respectées (classe dans son
 * niveau, raison de la déscolarisation, année précédente…).
 *
 * Le calcul repart toujours de la fiche telle qu'elle était avant le premier enregistrement du suivi
 * (SuiviParrainage::fiche_avant) : corriger un suivi ne fait pas passer deux fois de classe.
 * Seul le suivi le plus récent de l'OEV modifie sa fiche.
 */
class FicheApresSuivi
{
    /** Champs de scolarité et de formation de la fiche que le suivi peut modifier. */
    public const CHAMPS = [
        'situation_scolaire', 'niveau_etude', 'classe', 'systeme_educatif', 'etablissement_actuel', 'type_etablissement',
        'frais_scolarite', 'performance_scolaire', 'performance_difficultes',
        'etablissement_precedent', 'classe_precedente', 'moyenne_annuelle', 'appreciation',
        'raison_non_scolarisation', 'raison_non_scolarisation_precision',
        'formation_professionnelle', 'formation_etat', 'formation_filiere', 'formation_type_centre', 'formation_duree_mois', 'formation_duree_recue_mois', 'frais_formation',
    ];

    /** Champs de l'école en cours, vidés quand l'enfant ne la fréquente plus. */
    private const ECOLE_EN_COURS = ['systeme_educatif', 'etablissement_actuel', 'type_etablissement', 'classe', 'frais_scolarite', 'performance_scolaire', 'performance_difficultes'];

    /** Scolarité de la fiche à conserver avant le premier enregistrement d'un suivi. */
    public static function photographier(Oev $oev): array
    {
        return collect(self::CHAMPS)->mapWithKeys(fn ($champ) => [$champ => $oev->getAttributes()[$champ] ?? null])->all();
    }

    /** Classe de l'année suivie (celle de la fiche avant le suivi), pour calculer la classe suivante. */
    public static function classeSuivie(Oev $oev, ?SuiviParrainage $suivi): ?string
    {
        return $suivi?->fiche_avant !== null && array_key_exists('classe', $suivi->fiche_avant) ? $suivi->fiche_avant['classe'] : $oev->classe;
    }

    /**
     * Applique le suivi à la fiche de l'OEV.
     *
     * @return string|null ce qui a changé sur la fiche, ou null si elle n'est pas modifiée (suivi d'une année passée)
     */
    public function appliquer(SuiviParrainage $suivi): ?string
    {
        $oev = $suivi->oev;
        if (SuiviParrainage::where('oev_id', $oev->id)->where('annee', '>', $suivi->annee)->exists()) {
            return null;
        }

        $avant = $suivi->fiche_avant ?? self::photographier($oev);
        $nouveau = $suivi->type_appui === SessionParrainage::TYPE_FORMATION
            ? $this->apresFormation($suivi, $avant)
            : $this->apresAnneeScolaire($suivi, $avant);

        // On repart de la fiche d'avant le suivi, puis on applique la nouvelle situation
        $oev->forceFill($avant)->forceFill($nouveau)->save();

        return $suivi->libelleProchaineAnnee() ?? $suivi->libelleResultat();
    }

    private function apresAnneeScolaire(SuiviParrainage $suivi, array $avant): array
    {
        $etaitScolarise = ($avant['situation_scolaire'] ?? null) === 'scolarise' && ($avant['classe'] ?? null) !== null;

        // L'année suivie devient « l'année précédente » de la fiche
        $anneePrecedente = $etaitScolarise ? [
            'etablissement_precedent' => $avant['etablissement_actuel'] ?? $avant['etablissement_precedent'] ?? null,
            'classe_precedente' => $avant['classe'],
            'moyenne_annuelle' => $suivi->moyenne,
            'appreciation' => match ($suivi->resultat) {
                'admis' => 'admis',
                'redouble' => 'redouble',
                default => null,
            },
        ] : [];

        $details = $suivi->details_prochaine_annee ?? [];

        if ($suivi->orientation === SuiviParrainage::ECOLE) {
            $classe = $suivi->prochaine_annee;

            return $anneePrecedente + [
                'situation_scolaire' => 'scolarise',
                'classe' => $classe,
                'niveau_etude' => Oev::niveauDeLaClasse($classe) ?? ($avant['niveau_etude'] ?? null),
                'raison_non_scolarisation' => null,
                'raison_non_scolarisation_precision' => null,
                // Établissement et frais de l'année prochaine, saisis au suivi
                'etablissement_actuel' => $details['etablissement'] ?? ($avant['etablissement_actuel'] ?? null),
                'type_etablissement' => $details['type_etablissement'] ?? ($avant['type_etablissement'] ?? null),
                'frais_scolarite' => $details['frais'] ?? null,
            ];
        }

        // Formation professionnelle ou arrêt : l'enfant quitte l'école
        $quitte = $etaitScolarise ? array_fill_keys(self::ECOLE_EN_COURS, null) + [
            'situation_scolaire' => 'descolarise',
            'niveau_etude' => Oev::niveauDeLaClasse($avant['classe']) ?? ($avant['niveau_etude'] ?? null),
            'raison_non_scolarisation' => 'autre',
            'raison_non_scolarisation_precision' => match (true) {
                $suivi->orientation === SuiviParrainage::FORMATION => "Orientation vers une formation professionnelle ({$suivi->annee})",
                $suivi->resultat === 'abandon' => "Abandon de l’école en {$suivi->annee}",
                default => "Arrêt de l’école après l’année {$suivi->annee}",
            },
        ] : [];

        if ($suivi->orientation === SuiviParrainage::FORMATION) {
            return $anneePrecedente + $quitte + [
                'formation_professionnelle' => true,
                'formation_etat' => 'en_cours',
                'formation_filiere' => $suivi->prochaine_annee,
                'formation_type_centre' => $details['type_centre'] ?? null,
                'formation_duree_mois' => $details['duree_mois'] ?? null,
                'formation_duree_recue_mois' => 0,
                'frais_formation' => $details['frais'] ?? null,
            ];
        }

        return $anneePrecedente + $quitte;
    }

    private function apresFormation(SuiviParrainage $suivi, array $avant): array
    {
        return match ($suivi->resultat) {
            // Formation achevée : toute la durée a été reçue
            'achevee' => [
                'formation_etat' => 'achevee',
                'formation_duree_recue_mois' => $avant['formation_duree_mois'] ?? $avant['formation_duree_recue_mois'] ?? null,
            ],
            // Abandon : l'OEV n'est plus en formation (l'historique reste dans le suivi et le parcours)
            'abandon' => array_fill_keys(['formation_etat', 'formation_filiere', 'formation_type_centre', 'formation_duree_mois', 'formation_duree_recue_mois', 'frais_formation'], null)
                + ['formation_professionnelle' => false],
            // Poursuit : la fiche reste « en formation », avec les frais de l'année prochaine
            default => [
                'formation_professionnelle' => true,
                'formation_etat' => 'en_cours',
                'frais_formation' => ($suivi->details_prochaine_annee ?? [])['frais'] ?? ($avant['frais_formation'] ?? null),
            ],
        };
    }
}
