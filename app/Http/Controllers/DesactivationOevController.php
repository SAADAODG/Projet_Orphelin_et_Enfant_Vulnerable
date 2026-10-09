<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\Oev;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Désactivation d'un OEV intégré (décès, majorité…) : il ne bénéficie plus d'aucune aide.
 *
 * Le DP la demande pour un OEV de sa province ; le niveau central la valide ou la refuse, peut
 * désactiver directement et réactiver un OEV désactivé par erreur. Chaque étape est tracée.
 */
class DesactivationOevController extends Controller
{
    /** DP : demande avec motif. Niveau central : désactivation immédiate. */
    public function desactiver(Request $request, Oev $oev): RedirectResponse
    {
        $utilisateur = $request->user();
        $central = $utilisateur->can('intégrer OEV');
        abort_unless($central || $utilisateur->can('constituer dossiers'), 403);
        abort_unless($oev->estDansLePerimetreDe($utilisateur), 403);

        if (! $oev->estIntegre() || $oev->desactivation_etat !== null) {
            return back()->withErrors(['desactivation' => 'Seul un OEV intégré et actif peut être désactivé.']);
        }

        $donnees = $request->validate([
            'desactivation_motif' => ['required', Rule::in(array_keys(Oev::MOTIFS_DESACTIVATION))],
            'desactivation_commentaire' => [Rule::requiredIf($request->input('desactivation_motif') === 'autre'), 'nullable', 'string', 'max:2000'],
        ], [
            'desactivation_motif.required' => 'Choisissez le motif de la désactivation.',
            'desactivation_commentaire.required' => 'Précisez le motif de la désactivation.',
        ]);

        $oev->update($donnees + [
            'desactivation_etat' => $central ? Oev::DESACTIVE : Oev::DESACTIVATION_DEMANDEE,
            'desactivation_demandee_at' => now(),
            'desactivation_demandee_par' => $utilisateur->id,
            'desactive_at' => $central ? now() : null,
            'desactive_par' => $central ? $utilisateur->id : null,
        ]);

        JournalActivite::consigner(
            $central ? 'oev.desactivation' : 'oev.desactivation_demandee',
            $oev,
            ($central ? 'Désactivation de l’OEV ' : 'Demande de désactivation de l’OEV ') . $oev->reference() . ' : ' . $oev->libelleMotifDesactivation(),
            array_filter(['motif' => $oev->desactivation_motif, 'commentaire' => $oev->desactivation_commentaire]),
        );

        return back()->with('success', $central
            ? 'OEV désactivé : il ne bénéficiera plus d’aucune aide.'
            : 'Demande de désactivation transmise au niveau central.');
    }

    /** Niveau central : valide ou refuse la demande du DP. */
    public function decider(Request $request, Oev $oev): RedirectResponse
    {
        abort_unless($request->user()->can('intégrer OEV'), 403);

        if (! $oev->desactivationDemandee()) {
            return back()->withErrors(['desactivation' => 'Aucune demande de désactivation n’est en attente pour cet OEV.']);
        }

        $donnees = $request->validate([
            'decision' => ['required', Rule::in(['valider', 'refuser'])],
            'motif_refus' => ['required_if:decision,refuser', 'nullable', 'string', 'max:2000'],
        ], ['motif_refus.required_if' => 'Indiquez le motif du refus, transmis au DP.']);

        if ($donnees['decision'] === 'valider') {
            $oev->update(['desactivation_etat' => Oev::DESACTIVE, 'desactive_at' => now(), 'desactive_par' => $request->user()->id]);
            JournalActivite::consigner('oev.desactivation', $oev, "Désactivation de l’OEV {$oev->reference()} validée : {$oev->libelleMotifDesactivation()}");

            return back()->with('success', 'Désactivation validée : l’OEV ne bénéficiera plus d’aucune aide.');
        }

        JournalActivite::consigner('oev.desactivation_refusee', $oev, "Demande de désactivation de l’OEV {$oev->reference()} refusée", [
            'motif_demande' => $oev->desactivation_motif,
            'motif' => $donnees['motif_refus'],
        ]);
        $oev->update($this->champsVides());

        return back()->with('success', 'Demande de désactivation refusée : l’OEV reste bénéficiaire.');
    }

    /** Niveau central : réactivation (désactivation faite par erreur), motif obligatoire. */
    public function reactiver(Request $request, Oev $oev): RedirectResponse
    {
        abort_unless($request->user()->can('intégrer OEV'), 403);

        if (! $oev->estDesactive()) {
            return back()->withErrors(['desactivation' => 'Cet OEV n’est pas désactivé.']);
        }

        $donnees = $request->validate(
            ['motif_reactivation' => ['required', 'string', 'max:2000']],
            ['motif_reactivation.required' => 'Indiquez le motif de la réactivation.'],
        );

        JournalActivite::consigner('oev.reactivation', $oev, "Réactivation de l’OEV {$oev->reference()}", [
            'ancien_motif' => $oev->desactivation_motif,
            'motif' => $donnees['motif_reactivation'],
        ]);
        $oev->update($this->champsVides());

        return back()->with('success', 'OEV réactivé : il peut de nouveau bénéficier des aides.');
    }

    private function champsVides(): array
    {
        return array_fill_keys([
            'desactivation_etat', 'desactivation_motif', 'desactivation_commentaire',
            'desactivation_demandee_at', 'desactivation_demandee_par', 'desactive_at', 'desactive_par',
        ], null);
    }
}
