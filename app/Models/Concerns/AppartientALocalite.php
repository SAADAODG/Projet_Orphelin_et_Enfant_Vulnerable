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

    /**
     * Région, province ou commune dont le nom contient le terme (« %terme% »), sans tenir compte de la casse
     * (à utiliser dans un orWhere). Les localités correspondantes sont lues d'abord (petites tables) : la
     * condition devient « region_id IN (…) », que la base combine avec les index des autres critères de la
     * recherche (une sous-requête EXISTS l'obligerait à examiner chaque dossier).
     */
    public function scopeOuLocaliteContient(Builder $query, string $terme): Builder
    {
        foreach (['region', 'province', 'commune'] as $relation) {
            $liaison = $query->getModel()->{$relation}();
            $ids = $liaison->getRelated()->newQuery()->whereLike('nom', $terme)->pluck($liaison->getOwnerKeyName())->all();
            if ($ids) {
                $query->orWhereIn($query->qualifyColumn($liaison->getForeignKeyName()), $ids);
            }
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
