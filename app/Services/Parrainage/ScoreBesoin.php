<?php

namespace App\Services\Parrainage;

use App\Models\Oev;

/**
 * Score d'urgence du besoin d'un OEV (0 à 100), pour classer les éligibles d'une session :
 * plus le score est élevé, plus l'enfant est prioritaire. Calculé à partir de la fiche d'identification.
 *
 * Le même barème sert au calcul en base (tri et filtre de la sélection) et au détail affiché.
 */
final class ScoreBesoin
{
    /** Critère => [libellé, champ, [valeur => points]] ; total maximal : 100. */
    public const BAREME = [
        'priorite' => ['Priorité du cas', 'niveau_priorite', ['eleve' => 20, 'moyen' => 12, 'faible' => 4]],
        'statut' => ['Situation d’orphelin', 'statut', ['orphelin_double' => 20, 'orphelin_mere' => 14, 'orphelin_pere' => 12, 'vulnerable' => 6]],
        'source_revenu' => ['Source du revenu', 'source_revenu', ['mendicite' => 10, 'ong_services' => 7, 'aide_famille' => 4, 'fonds_propres' => 0]],
        'niveau_revenu' => ['Niveau du revenu', 'niveau_revenu', ['faible' => 10, 'moyen' => 5, 'eleve' => 0]],
        'logement' => ['Logement', 'logement', ['sans_abri' => 10, 'abri_precaire' => 8, 'site_deplaces' => 8, 'zone_non_lotie' => 5, 'location' => 2, 'proprietaire' => 0]],
    ];

    /** Oui / non : critère => [libellé, champ, points si vrai]. */
    public const BAREME_OUI = [
        'handicap' => ['Situation de handicap', 'handicap', 10],
        'maladie' => ['Maladie', 'maladie_chronique', 5],
    ];

    /** Vulnérabilités graves : 2 points chacune (10 au plus). */
    public const VULNERABILITES_GRAVES = ['abandon', 'non_accompagne', 'travail', 'enfant_marie', 'detresse'];
    public const POINTS_VULNERABILITE = 2;

    /** Le parent / tuteur ne peut plus s'occuper de l'enfant. */
    public const POINTS_TUTEUR_ARRET = 5;

    public const NIVEAUX = [
        'critique' => [60, 'Critique', 'danger'],
        'eleve' => [40, 'Élevé', 'warning'],
        'modere' => [20, 'Modéré', 'info'],
        'faible' => [0, 'Faible', 'secondary'],
    ];

    /** Expression SQL du score, pour la colonne « score » d'une requête sur la table oevs. */
    public static function sql(string $table = 'oevs'): string
    {
        $morceaux = [];
        foreach (self::BAREME as [, $champ, $points]) {
            $cas = collect($points)->filter()->map(fn ($p, $valeur) => "WHEN '{$valeur}' THEN {$p}")->implode(' ');
            $morceaux[] = "(CASE {$table}.{$champ} {$cas} ELSE 0 END)";
        }
        foreach (self::BAREME_OUI as [, $champ, $points]) {
            $morceaux[] = "(CASE WHEN {$table}.{$champ} = 1 THEN {$points} ELSE 0 END)";
        }
        foreach (self::VULNERABILITES_GRAVES as $code) {
            $morceaux[] = "(CASE WHEN {$table}.vulnerabilites LIKE '%\"{$code}\"%' THEN " . self::POINTS_VULNERABILITE . ' ELSE 0 END)';
        }
        $morceaux[] = "(CASE WHEN {$table}.tuteur_pret_continuer = 0 THEN " . self::POINTS_TUTEUR_ARRET . ' ELSE 0 END)';

        return implode(' + ', $morceaux);
    }

    /**
     * Détail du score d'un OEV : libellé => points (critères à 0 omis).
     *
     * @return array<string, int>
     */
    public static function detail(Oev $oev): array
    {
        $detail = [];
        foreach (self::BAREME as [$libelle, $champ, $points]) {
            $detail[$libelle] = $points[(string) $oev->{$champ}] ?? 0;
        }
        foreach (self::BAREME_OUI as [$libelle, $champ, $points]) {
            $detail[$libelle] = $oev->{$champ} ? $points : 0;
        }
        $graves = array_intersect(self::VULNERABILITES_GRAVES, (array) $oev->vulnerabilites);
        $detail['Vulnérabilités graves'] = count($graves) * self::POINTS_VULNERABILITE;
        $detail['Tuteur ne peut plus s’en occuper'] = $oev->tuteur_pret_continuer === false ? self::POINTS_TUTEUR_ARRET : 0;

        return array_filter($detail);
    }

    public static function calculer(Oev $oev): int
    {
        return array_sum(self::detail($oev));
    }

    /** [libellé, couleur Bootstrap] du niveau d'urgence correspondant à un score. */
    public static function niveau(int $score): array
    {
        foreach (self::NIVEAUX as [$seuil, $libelle, $couleur]) {
            if ($score >= $seuil) {
                return [$libelle, $couleur];
            }
        }

        return ['Faible', 'secondary'];
    }
}
