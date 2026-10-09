<?php

namespace App\Services\Parrainage;

use App\Models\Oev;
use App\Models\SessionParrainage;
use Illuminate\Support\Collection;

/**
 * Implémentation provisoire en attendant les modules de Nakoulma : aucune liste n'est disponible.
 *
 * Seule exception, eligiblesParRegion() : elle compte les OEV intégrés et non désactivés de chaque région, faute de
 * critères d'éligibilité définis par le module Sélection. À remplacer par le calcul de ce module.
 */
class SourceSelectionNonDisponible implements SourceSelection
{
    public function disponible(): bool
    {
        return false;
    }

    public function eligiblesParRegion(SessionParrainage $session): array
    {
        return Oev::beneficiaires()
            ->whereNotNull('region_id')
            ->selectRaw('region_id, count(*) as total')
            ->groupBy('region_id')
            ->pluck('total', 'region_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function montantEngage(SessionParrainage $session): ?int
    {
        return null;
    }

    public function listeDefinitive(SessionParrainage $session): Collection
    {
        return collect();
    }

    public function listeAttente(SessionParrainage $session): Collection
    {
        return collect();
    }

    public function etablissements(): Collection
    {
        return collect();
    }

    public function naturesAppuiEtat(int $oevId, string $annee): array
    {
        return [];
    }

    public function resultatsScolaires(string $annee): Collection
    {
        return collect();
    }
}
