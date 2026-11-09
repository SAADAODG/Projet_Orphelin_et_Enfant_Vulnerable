<?php

namespace App\Models\Concerns\Oev;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dossier OEV (App\Models\Oev) — Circuit du dossier : numéros, états, droits de modification et de rejet, visibilité selon le rôle et la zone.
 * Les constantes (états, listes) restent dans le modèle : self:: les désigne.
 */
trait Circuit
{
    /** Code OEV attribué à l'intégration, ex. OEV-2026-0001. */
    public static function genererCode(): string
    {
        return self::prochainNumero('code', 'OEV');
    }

    /** Numéro de dossier attribué à la constitution, ex. DOS-2026-0001. */
    public static function genererNumeroDossier(): string
    {
        return self::prochainNumero('numero_dossier', 'DOS');
    }

    private static function prochainNumero(string $colonne, string $prefixe): string
    {
        $prefixe .= '-' . now()->year . '-';
        $dernier = static::withTrashed()->where($colonne, 'like', $prefixe . '%')->orderByDesc($colonne)->value($colonne);
        $numero = $dernier ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    /** Identifiant affiché : le code OEV une fois intégré, sinon le numéro de dossier. */
    public function reference(): string
    {
        return $this->code ?: (string) $this->numero_dossier;
    }

    public function estIntegre(): bool
    {
        return $this->statut_dossier === self::ETAT_INTEGRE;
    }

    /** Le DP peut modifier, compléter, supprimer ou soumettre le dossier. */
    public function estModifiable(): bool
    {
        return in_array($this->statut_dossier, [self::ETAT_BROUILLON, self::ETAT_NON_CONFORME], true);
    }

    /** Le DR doit traiter le dossier : soumis par le DP, ou revenu du central avec une demande de complément. */
    public function attendDecisionDr(): bool
    {
        return in_array($this->statut_dossier, [self::ETAT_SOUMIS, self::ETAT_COMPLEMENT], true);
    }

    /**
     * Niveau habilité à rejeter le dossier à son étape actuelle, pour cet utilisateur :
     * « DR » (dossier soumis ou en complément), « Central » (dossier validé), sinon null.
     */
    public function niveauRejetPour(?User $utilisateur): ?string
    {
        return match (true) {
            $utilisateur === null => null,
            $this->attendDecisionDr() && $utilisateur->can(Permissions::VALIDER_DOSSIERS) => self::REJET_DR,
            $this->statut_dossier === self::ETAT_VALIDE && $utilisateur->can(Permissions::INTEGRER_OEV) => self::REJET_CENTRAL,
            default => null,
        };
    }

    /**
     * Qui peut modifier les informations et les pièces, et quand :
     * le DP pendant la constitution (ou après non-conformité), le DR quand le central demande un complément.
     */
    public function peutEtreModifiePar(?User $utilisateur): bool
    {
        if (! $utilisateur) {
            return false;
        }

        return ($this->estModifiable() && $utilisateur->can(Permissions::CONSTITUER_DOSSIERS))
            || ($this->statut_dossier === self::ETAT_COMPLEMENT && $utilisateur->can(Permissions::VALIDER_DOSSIERS));
    }

    public function libelleEtat(): string
    {
        return self::ETATS[$this->statut_dossier] ?? (string) $this->statut_dossier;
    }

    public function couleurEtat(): string
    {
        return self::COULEURS_ETATS[$this->statut_dossier] ?? 'secondary';
    }

    public function scopeEtat(Builder $query, string ...$etats): Builder
    {
        return $query->whereIn('statut_dossier', $etats);
    }

    /**
     * États du circuit visibles par l'utilisateur (null = tous) : le DP voit tous les dossiers,
     * le DR à partir de leur soumission, le niveau central une fois validés par le DR ;
     * les autres rôles ne voient que les enfants intégrés. La supervision voit tout.
     */
    public static function etatsVisiblesPar(User $utilisateur): ?array
    {
        return match (true) {
            $utilisateur->supervise(), $utilisateur->can(Permissions::CONSTITUER_DOSSIERS) => null,
            $utilisateur->can(Permissions::VALIDER_DOSSIERS) => [self::ETAT_SOUMIS, self::ETAT_NON_CONFORME, self::ETAT_VALIDE, self::ETAT_COMPLEMENT, self::ETAT_INTEGRE, self::ETAT_REJETE],
            $utilisateur->can(Permissions::INTEGRER_OEV) => [self::ETAT_VALIDE, self::ETAT_COMPLEMENT, self::ETAT_INTEGRE, self::ETAT_REJETE],
            default => [self::ETAT_INTEGRE],
        };
    }

    /**
     * Dossiers visibles par l'utilisateur : ceux de sa zone, à l'étape où il intervient.
     * Parmi les dossiers rejetés, le niveau central ne voit que ceux qu'il a lui-même rejetés :
     * un rejet du DR porte sur un dossier qui n'est jamais arrivé au central.
     */
    public function scopeVisiblesPar(Builder $query, User $utilisateur): Builder
    {
        $etats = self::etatsVisiblesPar($utilisateur);
        $centralSeul = ! $utilisateur->supervise()
            && ! $utilisateur->canAny([Permissions::CONSTITUER_DOSSIERS, Permissions::VALIDER_DOSSIERS])
            && $utilisateur->can(Permissions::INTEGRER_OEV);

        return $query->dansLePerimetreDe($utilisateur)
            ->when($etats !== null, fn ($q) => $q->whereIn('statut_dossier', $etats))
            ->when($centralSeul, fn ($q) => $q->where(fn ($q) => $q
                ->where('statut_dossier', '!=', self::ETAT_REJETE)
                ->orWhere('rejete_niveau', self::REJET_CENTRAL)));
    }

    public function estVisiblePar(User $utilisateur): bool
    {
        return static::query()->visiblesPar($utilisateur)->whereKey($this->getKey())->exists();
    }
}
