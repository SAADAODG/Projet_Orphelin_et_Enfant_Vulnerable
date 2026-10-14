<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * École du référentiel (Paramètres > Écoles), proposée dans la liste des établissements
 * du formulaire OEV selon la commune de l'enfant.
 */
class Ecole extends Model
{
    protected $fillable = [
        'commune_id',
        'ceb',
        'nom',
        'statut',
        'type',
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

    /** Une école choisie par un dossier OEV (même supprimé logiquement) ne peut pas être supprimée. */
    public function estUtiliseeParDesDossiers(): bool
    {
        return Oev::withTrashed()->where('ecole_id', $this->getKey())->exists();
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
