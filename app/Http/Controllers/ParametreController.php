<?php

namespace App\Http\Controllers;

use App\Models\QuickLink;
use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParametreController extends Controller
{
    /**
     * Affiche la page « Paramètres généraux » : identité du site, structure, apparence, contact,
     * liens rapides et services affichés sur le site public.
     */
    public function edit(): View
    {
        return view('parametres.index', [
            'setting' => SiteSetting::current(),
            'quickLinks' => QuickLink::orderBy('ordre')->get(),
            'services' => Service::orderBy('ordre')->get(),
        ]);
    }

    /**
     * Met à jour l'identité, la structure et les coordonnées du site.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom_site' => ['required', 'string', 'max:255'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'structure_nom' => ['required', 'string', 'max:255'],
            'structure_nom_complet' => ['nullable', 'string', 'max:255'],
            'structure_description' => ['nullable', 'string', 'max:2000'],
            'ministere_tutelle' => ['nullable', 'string', 'max:255'],
            'police_admin' => ['required', 'string', Rule::in(array_keys(config('fonts')))],
            'police_public' => ['required', 'string', Rule::in(array_keys(config('fonts')))],
            'contact_adresse' => ['nullable', 'string', 'max:255'],
            'contact_telephone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        SiteSetting::current()->update($data);

        return redirect()->route('parametres.edit')
            ->with('success', 'Paramètres du site mis à jour avec succès.');
    }
}
