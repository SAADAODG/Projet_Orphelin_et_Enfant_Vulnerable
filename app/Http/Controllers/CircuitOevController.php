<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CircuitDuDossier;
use App\Models\Oev;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

/**
 * Circuit du dossier enfant : DP (soumettre) → DR (conforme, non conforme, rejet) → niveau central
 * (intégrer, demander un complément, rejet). La saisie du dossier est dans OevController.
 */
class CircuitOevController extends Controller implements HasMiddleware
{
    use CircuitDuDossier;

    /** DP : transmet le dossier complet au DR pour vérification. */
    public function soumettre(Request $request, Oev $oev)
    {
        if ($refus = $this->refuserSiVerrouille($oev)) {
            return $refus;
        }
        if (! $oev->estComplet()) {
            return back()->withErrors(['circuit' => 'Le dossier ne peut pas être soumis au DR : il manque ' . $this->piecesManquantes($oev) . '.']);
        }

        $this->transmettreAuDr($request, $oev);

        return redirect()->route('oevs.show', $oev)->with('success', 'Dossier soumis au DR pour vérification.');
    }

    /** DR : dossier conforme (ou complété après demande du central) → validé, transmis au niveau central. */
    public function conforme(Request $request, Oev $oev)
    {
        if (! $oev->attendDecisionDr()) {
            return back()->withErrors(['circuit' => 'Seul un dossier soumis au DR, ou revenu du central pour complément, peut être validé.']);
        }
        $apresComplement = $oev->statut_dossier === Oev::ETAT_COMPLEMENT;

        $oev->update([
            'statut_dossier' => Oev::ETAT_VALIDE,
            'verifie_at' => now(),
            'verifie_par' => $request->user()->id,
            'motif_non_conformite' => null,
        ]);

        return redirect()->route('oevs.validation')->with('success', $apresComplement
            ? "Dossier {$oev->numero_dossier} complété et renvoyé au niveau central."
            : "Dossier {$oev->numero_dossier} déclaré conforme et validé.");
    }

    /** DR : dossier non conforme (ou complément impossible à apporter par le DR) → renvoyé au DP avec le motif. */
    public function nonConforme(Request $request, Oev $oev)
    {
        if (! $oev->attendDecisionDr()) {
            return back()->withErrors(['circuit' => 'Seul un dossier soumis au DR, ou revenu du central pour complément, peut être renvoyé au DP.']);
        }
        $validated = $request->validate(
            ['motif_non_conformite' => ['required', 'string', 'max:2000']],
            ['motif_non_conformite.required' => 'Indiquez le motif de non-conformité pour que le DP puisse corriger le dossier.'],
        );

        $oev->update([
            'statut_dossier' => Oev::ETAT_NON_CONFORME,
            'verifie_at' => now(),
            'verifie_par' => $request->user()->id,
            'motif_non_conformite' => $validated['motif_non_conformite'],
        ]);

        return redirect()->route('oevs.validation')->with('success', "Dossier {$oev->numero_dossier} renvoyé au DP pour correction.");
    }

    /**
     * Rejet définitif, avec motif : par le DR (dossier soumis ou en complément) ou par le central (dossier validé).
     * Le dossier est clos : l'enfant ne devient pas OEV et le dossier n'est plus modifiable.
     */
    public function rejeter(Request $request, Oev $oev)
    {
        $niveau = $oev->niveauRejetPour($request->user());
        if (! $niveau) {
            abort_unless($request->user()->canAny([Permissions::VALIDER_DOSSIERS, Permissions::INTEGRER_OEV]), 403);

            return back()->withErrors(['circuit' => "Ce dossier est « {$oev->libelleEtat()} » : il ne peut pas être rejeté à cette étape."]);
        }
        $validated = $request->validate(
            ['motif_rejet' => ['required', 'string', 'max:2000']],
            ['motif_rejet.required' => 'Indiquez le motif du rejet.'],
        );

        $oev->update([
            'statut_dossier' => Oev::ETAT_REJETE,
            'motif_rejet' => $validated['motif_rejet'],
            'rejete_niveau' => $niveau,
            'rejete_at' => now(),
            'rejete_par' => $request->user()->id,
        ]);

        return redirect()->route($niveau === Oev::REJET_DR ? 'oevs.validation' : 'oevs.integration')
            ->with('success', "Dossier {$oev->numero_dossier} rejeté.");
    }

    /** Niveau central : demande de complément sur un dossier validé → retour au DR avec la demande. */
    public function demanderComplement(Request $request, Oev $oev)
    {
        if ($oev->statut_dossier !== Oev::ETAT_VALIDE) {
            return back()->withErrors(['circuit' => 'Un complément ne peut être demandé que sur un dossier validé par le DR.']);
        }
        $validated = $request->validate(
            ['motif_complement' => ['required', 'string', 'max:2000']],
            ['motif_complement.required' => 'Précisez le complément attendu pour que le DR puisse y répondre.'],
        );

        $oev->update([
            'statut_dossier' => Oev::ETAT_COMPLEMENT,
            'motif_complement' => $validated['motif_complement'],
            'complement_at' => now(),
            'complement_par' => $request->user()->id,
        ]);

        return redirect()->route('oevs.integration')->with('success', "Complément demandé : le dossier {$oev->numero_dossier} est renvoyé au DR.");
    }

    /** Niveau central : l'enfant est intégré et devient OEV (attribution du code OEV). */
    public function integrer(Request $request, Oev $oev)
    {
        if ($oev->statut_dossier !== Oev::ETAT_VALIDE) {
            return back()->withErrors(['circuit' => 'Seul un dossier validé par le DR peut être intégré.']);
        }

        DB::transaction(fn () => $oev->update([
            'code' => Oev::genererCode(),
            'statut_dossier' => Oev::ETAT_INTEGRE,
            'integre_at' => now(),
            'integre_par' => $request->user()->id,
        ]));

        return redirect()->route('oevs.show', $oev)->with('success', "{$oev->nomComplet()} est intégré(e) : code OEV {$oev->code}.");
    }
}
