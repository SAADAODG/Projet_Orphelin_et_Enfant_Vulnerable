<?php

namespace App\Services\Parrainage;

use App\Models\Oev;
use Illuminate\Support\Facades\DB;

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
    /** Écriture portable (PostgreSQL, SQLite) : colonnes booléennes testées directement, JSON lu comme texte. */
    public static function sql(string $table = 'oevs'): string
    {
        $morceaux = [];
        foreach (self::BAREME as [, $champ, $points]) {
            $cas = collect($points)->filter()->map(fn ($p, $valeur) => "WHEN '{$valeur}' THEN {$p}")->implode(' ');
            $morceaux[] = "(CASE {$table}.{$champ} {$cas} ELSE 0 END)";
        }
        foreach (self::BAREME_OUI as [, $champ, $points]) {
            $morceaux[] = "(CASE WHEN {$table}.{$champ} THEN {$points} ELSE 0 END)";
        }
        foreach (self::VULNERABILITES_GRAVES as $code) {
            $morceaux[] = "(CASE WHEN CAST({$table}.vulnerabilites AS TEXT) LIKE '%\"{$code}\"%' THEN " . self::POINTS_VULNERABILITE . ' ELSE 0 END)';
        }
        $morceaux[] = "(CASE WHEN NOT {$table}.tuteur_pret_continuer THEN " . self::POINTS_TUTEUR_ARRET . ' ELSE 0 END)';

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

    /* ---------- Score enregistré (oevs.score_besoin) ---------- */

    /** Champs de la fiche dont dépend le score : leur modification le fait recalculer. */
    public const CHAMPS = [
        'niveau_priorite', 'statut', 'source_revenu', 'niveau_revenu', 'logement',
        'handicap', 'maladie_chronique', 'vulnerabilites', 'tuteur_pret_continuer',
    ];

    /** Paquets d'identifiants recalculés en une requête. */
    private const PAQUET = 5000;

    /** Mode différé : identifiants des fiches à recalculer à la fin (null : calcul immédiat). */
    private static ?array $differes = null;

    /**
     * Recalcule le score enregistré d'un OEV, en SQL direct (ni événement, ni journal d'audit) ; en mode
     * différé, le note pour la fin. La fiche en mémoire reçoit la nouvelle valeur.
     */
    public static function recalculer(Oev $oev): void
    {
        if (self::$differes !== null) {
            self::$differes[$oev->getKey()] = true;

            return;
        }
        self::recalculerIds([$oev->getKey()]);
        $oev->setRawAttributes(['score_besoin' => (int) Oev::whereKey($oev->getKey())->value('score_besoin')] + $oev->getAttributes(), true);
    }

    /**
     * Exécute $traitement (import, modification en masse…) sans recalcul fiche par fiche : les scores des
     * fiches enregistrées pendant le traitement sont recalculés à la fin, par paquets.
     */
    public static function differer(callable $traitement): mixed
    {
        if (self::$differes !== null) {
            return $traitement();
        }
        self::$differes = [];
        try {
            return $traitement();
        } finally {
            $ids = array_keys(self::$differes);
            self::$differes = null;
            foreach (array_chunk($ids, self::PAQUET) as $paquet) {
                self::recalculerIds($paquet);
            }
        }
    }

    /** Recalcule les scores enregistrés de ces fiches, par la base. */
    public static function recalculerIds(array $ids): void
    {
        if ($ids) {
            Oev::query()->whereKey($ids)->toBase()->update(['score_besoin' => DB::raw(self::sql())]);
        }
    }

    /** Recalcule le score de toutes les fiches (migration, après un import en SQL direct) ; renvoie le nombre de fiches. */
    public static function recalculerTout(): int
    {
        return DB::table('oevs')->update(['score_besoin' => DB::raw(self::sql())]);
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
