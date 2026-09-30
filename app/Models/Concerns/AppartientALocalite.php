<?php

namespace App\Models\Concerns;

use App\Models\Commune;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Support\Perimetre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Localité (Région > Province > Commune) d'un enregistrement, rattachée aux tables
 * regions / provinces / communes via region_id, province_id et commune_id.
 */
trait AppartientALocalite
{
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * Règles de validation de la localité : la province doit appartenir à la région choisie,
     * et la commune à la province choisie.
     */
    public static function reglesLocalite(Request $request, bool $requis = true): array
    {
        $presence = $requis ? 'required' : 'nullable';

        return [
            'region_id' => [$presence, 'integer', Rule::exists('regions', 'id')],
            'province_id' => [$presence, 'integer', Rule::exists('provinces', 'id')->where('region_id', $request->input('region_id'))],
            'commune_id' => [$presence, 'integer', Rule::exists('communes', 'id')->where('province_id', $request->input('province_id'))],
        ];
    }

    /** Limite aux enregistrements de la zone de l'utilisateur : sa province (DP), sa région (DR), tout le pays (niveau central). */
    public function scopeDansLePerimetreDe(Builder $query, User $utilisateur): Builder
    {
        return Perimetre::pour($utilisateur)->appliquer($query);
    }

    public function estDansLePerimetreDe(User $utilisateur): bool
    {
        return static::query()->dansLePerimetreDe($utilisateur)->whereKey($this->getKey())->exists();
    }

    /** Recherche par nom de région, de province ou de commune (à utiliser dans un orWhere). */
    public function scopeOuLocaliteContient(Builder $query, string $terme, string $operateur = 'like'): Builder
    {
        foreach (['region', 'province', 'commune'] as $relation) {
            $query->orWhereHas($relation, fn (Builder $q) => $q->where('nom', $operateur, $terme));
        }

        return $query;
    }

    /** Ex : « Ouagadougou, Kadiogo (Kadiogo) » — les niveaux absents sont ignorés. */
    public function localiteComplete(): string
    {
        $lieu = collect([$this->commune?->nom, $this->province?->nom])->filter()->implode(', ');

        return $this->region ? trim($lieu . ' (' . $this->region->nom . ')') : $lieu;
    }
}
