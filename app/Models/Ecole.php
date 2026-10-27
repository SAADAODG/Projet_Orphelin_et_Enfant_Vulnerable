<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Établissement du référentiel (Paramètres > Écoles et centres) : école, ou centre de formation
 * professionnelle. Proposé dans le formulaire OEV selon la commune de l'enfant ; sa banque et son RIB
 * servent au paiement des OEV parrainés qui le fréquentent.
 */
class Ecole extends Model
{
    public const NATURE_ECOLE = 'ecole';
    public const NATURE_CENTRE = 'centre_formation';

    public const NATURES = [self::NATURE_ECOLE => 'École', self::NATURE_CENTRE => 'Centre de formation professionnelle'];

    protected $fillable = [
        'nature',
        'commune_id',
        'ceb',
        'nom',
        'statut',
        'type',
        'banque',
        'rib',
    ];

    public const STATUTS = ['public' => 'Public', 'prive' => 'Privé', 'communautaire' => 'Communautaire'];

    public const TYPES = [
        'classique' => 'Classique',
        'franco_arabe' => 'Franco-arabe',
        'bilingue' => 'Bilingue',
        'multigrade' => 'Multigrade',
        'passerelle' => 'Passerelle',
        'inclusive' => 'Inclusive',
        'cebnf' => 'CEBNF',
        'cpaf' => 'CPAF',
        'afid_2' => 'AFID-2',
        'afid_4' => 'AFID-4',
        'autre' => 'Autre',
    ];

    /** Correspondances proposées dans le formulaire OEV : statut => public / privé, type => système éducatif. */
    public const TYPE_ETABLISSEMENT_OEV = ['public' => 'public', 'prive' => 'prive'];

    public const SYSTEME_EDUCATIF_OEV = ['classique' => 'classique', 'franco_arabe' => 'franco_arabe', 'bilingue' => 'classique'];

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementEtablissement::class, 'etablissement_id');
    }

    public function scopeNature(Builder $query, string $nature): Builder
    {
        return $query->where('nature', $nature);
    }

    /**
     * Un établissement choisi par un dossier OEV (même supprimé logiquement) ou payé par une session ne peut
     * pas être supprimé.
     */
    public function estUtiliseeParDesDossiers(): bool
    {
        return Oev::withTrashed()->where(fn ($q) => $q->where('ecole_id', $this->getKey())->orWhere('ecole_precedente_id', $this->getKey())
            ->orWhere('centre_formation_id', $this->getKey()))->exists()
            || BeneficiaireSession::where('etablissement_id', $this->getKey())->exists();
    }

    public function estUnCentre(): bool
    {
        return $this->nature === self::NATURE_CENTRE;
    }

    public function libelleNature(): string
    {
        return self::NATURES[$this->nature] ?? self::NATURES[self::NATURE_ECOLE];
    }

    public function libelleStatut(): ?string
    {
        return self::STATUTS[$this->statut] ?? null;
    }

    public function libelleType(): ?string
    {
        return self::TYPES[$this->type] ?? null;
    }
}
