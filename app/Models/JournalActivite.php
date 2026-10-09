<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Journal d'activités commun à tous les modules (traçabilité, RG-09).
 *
 * Usage depuis n'importe quel module :
 *   JournalActivite::consigner('session_parrainage.creation', $session, 'Création de la session 2026-2027 n°1');
 *   JournalActivite::consigner('extraction.paiement', null, 'Liste pour paiement', ['filtres' => $f, 'lignes' => 120]);
 *
 * Les entrées ne se modifient pas : le journal ne fait qu'ajouter.
 */
class JournalActivite extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal_activites';

    protected $fillable = ['user_id', 'action', 'sujet_type', 'sujet_id', 'description', 'details'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Ajoute une entrée au journal.
     *
     * @param  string  $action  Code « module.action », ex : session_parrainage.changement_etat
     * @param  Model|null  $sujet  Objet concerné (session, parrain, OEV…), null pour une action globale (extraction)
     * @param  string  $description  Phrase lisible affichée dans l'historique
     * @param  array  $details  Données utiles à l'audit : motif, filtres, ancien / nouvel état, nombre de lignes…
     * @param  User|null  $utilisateur  Auteur ; par défaut l'utilisateur connecté
     */
    public static function consigner(string $action, ?Model $sujet = null, string $description = '', array $details = [], ?User $utilisateur = null): self
    {
        return static::create([
            'user_id' => ($utilisateur ?? auth()->user())?->getKey(),
            'action' => $action,
            'sujet_type' => $sujet?->getMorphClass(),
            'sujet_id' => $sujet?->getKey(),
            'description' => mb_substr($description !== '' ? $description : $action, 0, 500),
            'details' => $details ?: null,
        ]);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }

    /** Entrées concernant un objet, les plus récentes d'abord. */
    public function scopeDe(Builder $query, Model $sujet): Builder
    {
        return $query->where('sujet_type', $sujet->getMorphClass())
            ->where('sujet_id', $sujet->getKey())
            ->latest('created_at')
            ->latest('id');
    }
}
