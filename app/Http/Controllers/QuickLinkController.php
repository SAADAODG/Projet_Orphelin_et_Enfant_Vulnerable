<?php

namespace App\Http\Controllers;

use App\Models\QuickLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuickLinkController extends Controller
{
    /**
     * Ajoute un nouveau lien rapide, à la suite des liens existants.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['ordre'] = ((int) QuickLink::max('ordre')) + 1;

        QuickLink::create($data);

        return redirect()->route('parametres.edit')->with('success', 'Lien rapide ajouté.');
    }

    /**
     * Met à jour le libellé et l'URL d'un lien rapide.
     */
    public function update(Request $request, QuickLink $quickLink): RedirectResponse
    {
        $quickLink->update($this->validateData($request));

        return redirect()->route('parametres.edit')->with('success', 'Lien rapide mis à jour.');
    }

    /**
     * Supprime un lien rapide.
     */
    public function destroy(QuickLink $quickLink): RedirectResponse
    {
        $quickLink->delete();

        return redirect()->route('parametres.edit')->with('success', 'Lien rapide supprimé.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:255'],
        ]);
    }
}
