<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Region;
use App\Models\User;
use App\Support\Perimetre;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Cloisonnement par zone, commun à tous les contrôleurs dont les données sont rattachées (directement ou
 * via l'OEV) à une localité : dossiers OEV, appuis, bénéficiaires et suivis de parrainage, signalements…
 *
 * La règle elle-même est dans App\Support\Perimetre (une seule source) :
 *  - DP : sa province ; DR : sa région ; niveau central et administrateurs : tout le pays ;
 *  - DP ou DR sans localité renseignée (compte mal configuré) : AUCUN enregistrement, jamais tout le pays.
 * Ce trait évite qu'un contrôleur la réimplémente à sa façon, ou l'oublie sur une action.
 *
 * Usage :
 *   $this->cloisonner(Oev::query())                      // liste d'un modèle portant region_id / province_id
 *   $this->cloisonner(AppuiPartenaire::query(), 'oev')   // via une relation menant à ce modèle
 *   $this->verifierZone($appui, 'oev')                   // fiche ou action sur un enregistrement : 403 hors zone
 */
trait CloisonneParZone
{
    /** Zone de l'utilisateur connecté (sans filtre choisi : tout le pays pour le niveau central). */
    protected function zone(): Perimetre
    {
        return Perimetre::pour($this->utilisateurConnecte());
    }

    /**
     * Restreint une requête à la zone de l'utilisateur connecté.
     *
     * @param  string|null  $relation  chemin de relation vers le modèle porteur de region_id / province_id
     *                                 (ex : « oev », « beneficiaire.oev ») ; null si la requête le porte elle-même
     */
    protected function cloisonner(Builder $requete, ?string $relation = null): Builder
    {
        $zone = $this->zone();

        return $relation === null
            ? $zone->appliquer($requete)
            : $requete->whereHas($relation, fn (Builder $q) => $zone->appliquer($q));
    }

    /**
     * Vérifie qu'UN enregistrement (fiche, modification, action) est dans la zone de l'utilisateur,
     * sinon abandonne la requête avec un 403.
     */
    protected function verifierZone(Model $enregistrement, ?string $relation = null, string $message = 'Cet enregistrement n’est pas dans votre zone.'): void
    {
        $dansLaZone = $this->cloisonner($enregistrement->newQuery()->whereKey($enregistrement->getKey()), $relation)->exists();

        abort_unless($dansLaZone, 403, $message);
    }

    /**
     * Filtre « Région / Province » des listes, limité à la zone de l'utilisateur : une région hors zone,
     * ou une province hors de la région choisie, est ignorée.
     *
     * @return array{localites: array, region_id: int|null, province_id: int|null, appliquer: Closure(Builder): Builder}
     */
    protected function filtreLocalite(Request $request, ?string $relation = null): array
    {
        $localites = $this->zone()->filtrerArborescence(Region::arborescence());
        $region = collect($localites)->firstWhere('id', $request->integer('region_id'));
        $regionId = $region['id'] ?? null;
        $provinceId = $region ? (collect($region['provinces'])->firstWhere('id', $request->integer('province_id'))['id'] ?? null) : null;

        $filtre = fn (Builder $q) => $q
            ->when($regionId, fn ($q, $id) => $q->where($q->qualifyColumn('region_id'), $id))
            ->when($provinceId, fn ($q, $id) => $q->where($q->qualifyColumn('province_id'), $id));

        return [
            'localites' => $localites,
            'region_id' => $regionId,
            'province_id' => $provinceId,
            'appliquer' => fn (Builder $q) => $relation === null ? $filtre($q) : $q->whereHas($relation, $filtre),
        ];
    }

    private function utilisateurConnecte(): User
    {
        /** @var User $utilisateur */
        $utilisateur = auth()->user();

        return $utilisateur;
    }
}
