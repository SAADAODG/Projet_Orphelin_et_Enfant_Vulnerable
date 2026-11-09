<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Oev;
use Closure;
use Illuminate\Http\Request;

/**
 * Commun aux contrôleurs d'un dossier enfant (OevController : saisie ; CircuitOevController : décisions du circuit).
 * Un dossier hors de la zone de l'utilisateur, ou pas encore arrivé à l'étape où il intervient, n'est pas accessible.
 */
trait CircuitDuDossier
{
    /** Middleware des actions sur un dossier ({oev} dans la route) : visibilité selon la zone et l'étape. */
    public static function middleware(): array
    {
        return [
            function (Request $request, Closure $next) {
                $oev = $request->route('oev');
                abort_if($oev instanceof Oev && ! $oev->estVisiblePar($request->user()), 403);

                return $next($request);
            },
        ];
    }

    /** Liste lisible des pièces exigées non encore fournies, ex. « l’acte de naissance, la photo ». */
    private function piecesManquantes(Oev $oev): string
    {
        $manquantes = array_diff_key($oev->piecesRequises(), array_flip($oev->piecesFournies()));

        return mb_strtolower(implode(', ', $manquantes));
    }

    private function transmettreAuDr(Request $request, Oev $oev): void
    {
        $oev->update([
            'statut_dossier' => Oev::ETAT_SOUMIS,
            'soumis_at' => now(),
            'soumis_par' => $request->user()->id,
        ]);
    }

    /** Un dossier soumis, validé ou intégré n'est plus modifiable par le DP. */
    private function refuserSiVerrouille(Oev $oev)
    {
        if ($oev->estModifiable()) {
            return null;
        }

        return redirect()->route('oevs.show', $oev)
            ->withErrors(['circuit' => "Ce dossier est « {$oev->libelleEtat()} » : il ne peut plus être modifié."]);
    }
}
