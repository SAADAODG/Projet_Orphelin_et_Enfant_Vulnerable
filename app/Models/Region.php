<?php

namespace App\Models;

use App\Models\Concerns\EstUtiliseeParDesDossiers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Region extends Model
{
    use EstUtiliseeParDesDossiers, HasFactory;

    protected $fillable = [
        'nom',
        'ancien_nom',
    ];

    /**
     * Les provinces rattachées à cette région.
     */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    /**
     * Les communes rattachées à cette région, via ses provinces.
     */
    public function communes(): HasManyThrough
    {
        return $this->hasManyThrough(Commune::class, Province::class);
    }

    /**
     * Arborescence complète Régions > Provinces > Communes > Villages, pour alimenter les listes
     * déroulantes en cascade (voir partials/localite-selects). Les ~11 000 villages ne sont
     * chargés que sur demande ($avecVillages), pour ne pas alourdir les autres formulaires.
     */
    public static function arborescence(bool $avecVillages = false): array
    {
        return static::with(array_filter([
            'provinces' => fn ($q) => $q->orderBy('nom'),
            'provinces.communes' => fn ($q) => $q->orderBy('nom'),
            'provinces.communes.villages' => $avecVillages ? fn ($q) => $q->orderBy('nom') : null,
        ]))
            ->orderBy('nom')
            ->get()
            ->map(fn (Region $region) => [
                'id' => $region->id,
                'nom' => $region->nom,
                'provinces' => $region->provinces->map(fn (Province $province) => [
                    'id' => $province->id,
                    'nom' => $province->nom,
                    'communes' => $province->communes->map(fn (Commune $commune) => array_filter([
                        'id' => $commune->id,
                        'nom' => $commune->nom,
                        'villages' => $avecVillages ? $commune->villages->map->only(['id', 'nom'])->values() : null,
                    ], fn ($valeur) => $valeur !== null))->values(),
                ])->values(),
            ])
            ->all();
    }
}
