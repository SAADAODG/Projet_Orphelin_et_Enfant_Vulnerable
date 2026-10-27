<?php

namespace App\Observers;

use App\Models\Oev;
use App\Services\Parrainage\PaiementsParrainage;

/**
 * Un OEV qui change d'école ou de centre de formation : les sessions de parrainage pas encore payées à son
 * ancien établissement le paieront au nouveau (voir PaiementsParrainage::suivreChangementEtablissement).
 */
class EtablissementOevObserver
{
    public function updated(Oev $oev): void
    {
        if ($oev->wasChanged(['ecole_id', 'centre_formation_id'])) {
            app(PaiementsParrainage::class)->suivreChangementEtablissement($oev);
        }
    }
}
