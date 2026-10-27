<?php

namespace App\Http\Controllers;

use App\Models\Oev;
use App\Models\ParametreOev;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Paramètres > âge des OEV : âge jusqu'auquel un enfant a le statut d'OEV, plus long pour un enfant
 * en situation de handicap. Au-delà, l'enfant n'est plus inscrit ni retenu pour une aide.
 */
class AgeOevController extends Controller
{
    public function edit(): View
    {
        return view('parametres.age-oev', [
            'ageLimite' => ParametreOev::ageLimite(),
            'ageLimiteHandicap' => ParametreOev::ageLimite(true),
            // OEV intégrés qui ont dépassé l'âge, à désactiver (motif « Devenu majeur »)
            'ageAtteint' => Oev::etat(Oev::ETAT_INTEGRE)
                ->where(fn ($q) => $q->whereNull('desactivation_etat')->orWhere('desactivation_etat', '!=', Oev::DESACTIVE))
                ->sortisParAge()
                ->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $bornes = ['required', 'integer', 'min:' . ParametreOev::AGE_MIN, 'max:' . ParametreOev::AGE_MAX];
        $donnees = $request->validate([
            'age_limite' => $bornes,
            'age_limite_handicap' => [...$bornes, 'gte:age_limite'],
        ], [
            'age_limite.required' => 'Indiquez l’âge de sortie.',
            'age_limite_handicap.required' => 'Indiquez l’âge de sortie pour un enfant en situation de handicap.',
            'age_limite_handicap.gte' => 'L’âge de sortie d’un enfant en situation de handicap ne peut pas être inférieur à l’âge général.',
            'min' => 'L’âge doit être compris entre ' . ParametreOev::AGE_MIN . ' et ' . ParametreOev::AGE_MAX . ' ans.',
            'max' => 'L’âge doit être compris entre ' . ParametreOev::AGE_MIN . ' et ' . ParametreOev::AGE_MAX . ' ans.',
        ]);

        ParametreOev::enregistrer(ParametreOev::AGE_LIMITE, $donnees['age_limite']);
        ParametreOev::enregistrer(ParametreOev::AGE_LIMITE_HANDICAP, $donnees['age_limite_handicap']);

        return redirect()->route('parametres.age-oev.edit')->with('success', 'Âges de sortie des OEV enregistrés.');
    }
}
