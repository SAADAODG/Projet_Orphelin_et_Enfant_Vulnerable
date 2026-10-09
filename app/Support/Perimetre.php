<?php

namespace App\Support;

use App\Models\Commune;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Zone géographique couverte par le tableau de bord : tout le pays, une région ou une province.
 *
 * Le DP est limité à sa province et le DR à sa région ; le niveau central voit tout le pays
 * et peut se restreindre à une région ou une province (paramètres region_id / province_id).
 */
final class Perimetre
{
    private function __construct(
        public readonly string $niveau,
        public readonly ?Region $region = null,
        public readonly ?Province $province = null,
        /** false quand un DR ou un DP n'est rattaché à aucune localité : rien ne doit lui être montré. */
        public readonly bool $defini = true,
    ) {
    }

    /** Sans $request (listes, contrôles d'accès), le niveau central couvre tout le pays. */
    public static function pour(User $utilisateur, ?Request $request = null): self
    {
        return match ($niveau = $utilisateur->niveau()) {
            User::NIVEAU_PROVINCE => $utilisateur->province
                ? new self($niveau, $utilisateur->province->region, $utilisateur->province)
                : new self($niveau, defini: false),
            User::NIVEAU_REGION => $utilisateur->region
                ? new self($niveau, $utilisateur->region)
                : new self($niveau, defini: false),
            default => self::filtreCentral($request),
        };
    }

    /** Filtre choisi par le niveau central ; une province hors de la région choisie est ignorée. */
    private static function filtreCentral(?Request $request): self
    {
        $region = Region::find($request?->integer('region_id') ?: null);
        $province = $region ? $region->provinces()->find($request->integer('province_id') ?: null) : null;

        return new self(User::NIVEAU_CENTRAL, $region, $province);
    }

    /** Restreint une requête sur un modèle portant region_id / province_id (OEV, signalement, plainte). */
    public function appliquer(Builder $query): Builder
    {
        return match (true) {
            ! $this->defini => $query->whereRaw('1 = 0'),
            $this->province !== null => $query->where($query->qualifyColumn('province_id'), $this->province->id),
            $this->region !== null => $query->where($query->qualifyColumn('region_id'), $this->region->id),
            default => $query,
        };
    }

    /**
     * Arborescence de localités (Region::arborescence()) réduite à la zone : la région du DR,
     * la province du DP ; tout le pays pour le niveau central, rien pour une zone non définie.
     */
    public function filtrerArborescence(array $localites): array
    {
        if (! $this->defini) {
            return [];
        }

        return collect($localites)
            ->when($this->region, fn ($regions) => $regions->where('id', $this->region->id))
            ->map(fn (array $region) => $this->province
                ? ['provinces' => collect($region['provinces'])->where('id', $this->province->id)->values()] + $region
                : $region)
            ->values()
            ->all();
    }

    public function libelle(): string
    {
        return match (true) {
            ! $this->defini => 'Aucune localité',
            $this->province !== null => "Province : {$this->province->nom}",
            $this->region !== null => "Région : {$this->region->nom}",
            default => 'Tout le pays',
        };
    }

    /** Colonne servant à ventiler les chiffres un cran plus fin : régions du pays, provinces d'une région, communes d'une province. */
    public function colonneDetail(): string
    {
        return match (true) {
            $this->province !== null => 'commune_id',
            $this->region !== null => 'province_id',
            default => 'region_id',
        };
    }

    public function libelleDetail(): string
    {
        return match ($this->colonneDetail()) {
            'commune_id' => 'Commune',
            'province_id' => 'Province',
            default => 'Région',
        };
    }

    /** Localités du niveau de détail, id => nom. */
    public function localitesDetail(): Collection
    {
        if (! $this->defini) {
            return collect();
        }

        return match ($this->colonneDetail()) {
            'commune_id' => Commune::where('province_id', $this->province->id)->orderBy('nom')->pluck('nom', 'id'),
            'province_id' => Province::where('region_id', $this->region->id)->orderBy('nom')->pluck('nom', 'id'),
            default => Region::orderBy('nom')->pluck('nom', 'id'),
        };
    }
}
