<?php

namespace App\Models\Concerns\Oev;

use App\Models\ParametreOev;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dossier OEV (App\Models\Oev) — Bénéficiaires, âge de sortie (Paramètres > Âge des OEV) et désactivation d’un OEV intégré.
 * Les constantes (états, listes) restent dans le modèle : self:: les désigne.
 */
trait AgeEtDesactivation
{
    /**
     * OEV pouvant bénéficier d'une aide (appui partenaire, parrainage de l'État) : intégrés, non désactivés
     * et sous l'âge de sortie (Paramètres > Âge des OEV). Un OEV dont la désactivation est seulement
     * demandée reste bénéficiaire jusqu'à la validation.
     */
    public function scopeBeneficiaires(Builder $query): Builder
    {
        return $query->etat(self::ETAT_INTEGRE)
            ->where(fn ($q) => $q->whereNull('desactivation_etat')->orWhere('desactivation_etat', '!=', self::DESACTIVE))
            ->sousAgeLimite();
    }

    /** OEV sous l'âge de sortie : plus élevé pour un enfant en situation de handicap. Sans date de naissance : retenu. */
    public function scopeSousAgeLimite(Builder $query): Builder
    {
        [$general, $handicap] = self::naissancesLimites();
        $colonne = $query->qualifyColumn('date_naissance');
        $colonneHandicap = $query->qualifyColumn('handicap');

        return $query->where(fn ($q) => $q->whereNull($colonne)
            ->orWhere(fn ($q) => $q->where($colonneHandicap, true)->whereDate($colonne, '>', $handicap))
            ->orWhere(fn ($q) => $q->where(fn ($q) => $q->whereNull($colonneHandicap)->orWhere($colonneHandicap, false))->whereDate($colonne, '>', $general)));
    }

    /** OEV qui ont atteint l'âge de sortie (le contraire de sousAgeLimite). */
    public function scopeSortisParAge(Builder $query): Builder
    {
        [$general, $handicap] = self::naissancesLimites();
        $colonne = $query->qualifyColumn('date_naissance');
        $colonneHandicap = $query->qualifyColumn('handicap');

        return $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->where($colonneHandicap, true)->whereDate($colonne, '<=', $handicap))
            ->orWhere(fn ($q) => $q->where(fn ($q) => $q->whereNull($colonneHandicap)->orWhere($colonneHandicap, false))->whereDate($colonne, '<=', $general)));
    }

    /** Dates de naissance à partir desquelles l'âge de sortie est atteint : [cas général, handicap]. */
    private static function naissancesLimites(): array
    {
        return [
            now()->subYears(ParametreOev::ageLimite())->toDateString(),
            now()->subYears(ParametreOev::ageLimite(true))->toDateString(),
        ];
    }

    /** Âge de sortie qui s'applique à cet enfant. */
    public function ageLimite(): int
    {
        return ParametreOev::ageLimite((bool) $this->handicap);
    }

    public function ageLimiteAtteint(): bool
    {
        return $this->date_naissance !== null && $this->age() >= $this->ageLimite();
    }

    public function estDesactive(): bool
    {
        return $this->desactivation_etat === self::DESACTIVE;
    }

    public function desactivationDemandee(): bool
    {
        return $this->desactivation_etat === self::DESACTIVATION_DEMANDEE;
    }

    public function libelleMotifDesactivation(): string
    {
        return self::MOTIFS_DESACTIVATION[$this->desactivation_motif] ?? (string) $this->desactivation_motif;
    }

    /** Âge en années révolues, calculé à partir de la date de naissance. */
    public function age(): int
    {
        return (int) $this->date_naissance?->age;
    }
}
