<?php

namespace App\Observers;

use App\Models\Oev;
use App\Services\Parrainage\ScoreBesoin;

/**
 * Tient à jour le score d'urgence enregistré (oevs.score_besoin) après chaque création ou modification
 * d'un critère du barème. Le calcul est fait en SQL direct : il ne déclenche aucun événement et n'apparaît
 * donc pas dans le journal d'audit. En mode différé (ScoreBesoin::differer), il est fait en une fois à la fin.
 */
class ScoreBesoinObserver
{
    public function created(Oev $oev): void
    {
        ScoreBesoin::recalculer($oev);
    }

    public function updated(Oev $oev): void
    {
        if ($oev->wasChanged(ScoreBesoin::CHAMPS)) {
            ScoreBesoin::recalculer($oev);
        }
    }
}
