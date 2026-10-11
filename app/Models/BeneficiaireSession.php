<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * OEV choisi pour une session de parrainage (module « Sélection ») : retenu sur la liste de la session
 * ou placé en liste d'attente (enveloppe ou quota régional atteint).
 */
class BeneficiaireSession extends Model
{
    public const RETENU = 'retenu';
    public const ATTENTE = 'attente';

    protected $table = 'beneficiaires_session';

    protected $fillable = [
        'session_parrainage_id', 'oev_id', 'type_appui', 'statut', 'rang', 'score', 'region_id',
        'frais_reels', 'frais_estimes', 'montant_retenu', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'frais_estimes' => 'boolean',
            'rang' => 'integer',
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

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function scopeRetenus(Builder $query): Builder
    {
        return $query->where('statut', self::RETENU);
    }

    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', self::ATTENTE);
    }

    /** Montant retenu : le plus petit des frais réels et du plafond ; le plafond quand les frais manquent. */
    public static function montantPour(?int $frais, int $plafond): int
    {
        return $frais === null ? $plafond : min($frais, $plafond);
    }
}
