<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** Engagement pris par un parrain : durée, natures d'appui proposées, nombre d'OEV et montant prévus. */
class EngagementParrain extends Model
{
    protected $table = 'engagements_parrain';

    protected $fillable = [
        'parrain_id', 'date_engagement', 'duree_mois', 'natures_appui', 'nombre_oev_prevu', 'montant_prevu',
        'convention_chemin', 'convention_nom', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_engagement' => 'date',
            'natures_appui' => 'array',
            'duree_mois' => 'integer',
            'nombre_oev_prevu' => 'integer',
            'montant_prevu' => 'integer',
        ];
    }

    public function parrain(): BelongsTo
    {
        return $this->belongsTo(Parrain::class);
    }

    /** Natures d'appui proposées, dans l'ordre de la table de référence. */
    public function natures(): Collection
    {
        return NatureAppui::whereIn('id', $this->natures_appui ?? [])->orderBy('ordre')->get();
    }
}
