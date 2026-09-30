<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Ajoute un nouveau service, à la suite des services existants.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['ordre'] = ((int) Service::max('ordre')) + 1;

        Service::create($data);

        return redirect()->route('parametres.edit')->with('success', 'Service ajouté.');
    }

    /**
     * Met à jour le libellé et l'URL d'un service.
     */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validateData($request));

        return redirect()->route('parametres.edit')->with('success', 'Service mis à jour.');
    }

    /**
     * Supprime un service.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('parametres.edit')->with('success', 'Service supprimé.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:255'],
        ]);
    }
}
