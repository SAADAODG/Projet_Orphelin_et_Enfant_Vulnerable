<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * OEV retenu sur la liste d'une session de parrainage (module « Sélection »), avec l'établissement à payer
 * (figé à la validation de la liste). Il n'y a pas de liste d'attente.
 */
class BeneficiaireSession extends Model
{
    public const RETENU = 'retenu';

    protected $table = 'beneficiaires_session';

    protected $fillable = [
        'session_parrainage_id', 'oev_id', 'type_appui', 'statut', 'score', 'region_id', 'etablissement_id',
        'frais_reels', 'frais_estimes', 'montant_retenu', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'frais_estimes' => 'boolean',
            'score' => 'integer',
            'frais_reels' => 'integer',
            'montant_retenu' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionParrainage::class, 'session_parrainage_id');
    }

    public function oev(): BelongsTo
    {
        return $this->belongsTo(Oev::class);
    }

    /** Suivi de fin d'année (OEV retenu sur une liste définitive). */
    public function suivi(): HasOne
    {
        return $this->hasOne(SuiviParrainage::class, 'beneficiaire_session_id');
    }

    /**
     * Parrainés : retenus sur la liste définitive d'une session validée (ou au-delà), qui doivent avoir un suivi.
     */
    public function scopeParraines(Builder $query): Builder
    {
        return $query->retenus()->whereHas('session', fn ($q) => $q->where('etat', '!=', SessionParrainage::ETAT_EN_COURS));
    }

    /** École ou centre de formation payé pour ce bénéficiaire. */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'etablissement_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function scopeRetenus(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('statut'), self::RETENU);
    }

    /**
     * Montant retenu : le plus petit des frais réels et du plafond ; le plafond quand les frais manquent.
     * Session sans plafond : les frais réels (0 s'ils manquent).
     */
    public static function montantPour(?int $frais, ?int $plafond): int
    {
        return match (true) {
            $frais === null => $plafond ?? 0,
            $plafond === null => $frais,
            default => min($frais, $plafond),
        };
    }
}
