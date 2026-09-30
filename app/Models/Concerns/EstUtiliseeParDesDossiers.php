<?php

namespace App\Models\Concerns;

use App\Models\Oev;
use App\Models\Plainte;
use App\Models\Signalement;

/**
 * Pour Region / Province / Commune : indique si la localité est référencée par un
 * signalement, une plainte ou un OEV (même supprimé logiquement), auquel cas elle ne
 * peut pas être supprimée (clé étrangère en restrictOnDelete).
 */
trait EstUtiliseeParDesDossiers
{
    public function estUtiliseeParDesDossiers(): bool
    {
        $colonne = $this->getForeignKey(); // region_id, province_id ou commune_id

        return Signalement::where($colonne, $this->getKey())->exists()
            || Plainte::where($colonne, $this->getKey())->exists()
            || Oev::withTrashed()->where($colonne, $this->getKey())->exists();
    }
}
