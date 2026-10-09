<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Appui d'un parrain à un OEV pendant une année scolaire. */
class AppuiPartenaire extends Model
{
    protected $table = 'appuis_partenaires';

    protected $fillable = [
        'oev_id', 'parrain_id', 'annee', 'nature_appui_id', 'nature_precision', 'montant', 'date_debut', 'date_fin',
        'observations', 'motif_doublon', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function oev(): BelongsTo
    {
        return $this->belongsTo(Oev::class);
    }

    public function parrain(): BelongsTo
    {
        return $this->belongsTo(Parrain::class);
    }

    public function nature(): BelongsTo
    {
        return $this->belongsTo(NatureAppui::class, 'nature_appui_id');
    }

    /** Appuis des OEV de la zone de l'utilisateur : sa province (DP), sa région (DR), tout le pays (central). */
    public function scopeDansLePerimetreDe(Builder $query, User $utilisateur): Builder
    {
        return $query->whereHas('oev', fn ($q) => $q->dansLePerimetreDe($utilisateur));
    }

    public function libelleNature(): string
    {
        $libelle = $this->nature?->libelle ?? '—';

        return $this->nature_precision ? "{$libelle} ({$this->nature_precision})" : $libelle;
    }
}
