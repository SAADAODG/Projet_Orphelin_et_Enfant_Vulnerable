<?php

namespace App\Http\Controllers;

use App\Models\Plainte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlainteController extends Controller
{
    /**
     * Formulaire public « Plainte ».
     */
    public function create(): View
    {
        return view('public.plainte', [
            'objets' => Plainte::OBJETS,
            'descriptions' => Plainte::OBJETS_DESCRIPTIONS,
            'icones' => Plainte::OBJETS_ICONES,
        ]);
    }

    /**
     * Enregistre le message de l'usager. L'identité et le contact sont facultatifs.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'objet' => ['required', Rule::in(array_keys(Plainte::OBJETS))],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'anonyme' => ['nullable', 'boolean'],
            'nom' => ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,}$/'],
            'email' => ['nullable', 'email', 'max:150'],
        ], [
            'required' => 'Ce champ est obligatoire.',
            'in' => 'Veuillez choisir une option valide.',
            'objet.required' => 'Choisissez le motif de votre message.',
            'description.min' => 'Merci de vous exprimer en quelques mots (10 caractères minimum).',
            'telephone.regex' => 'Numéro de téléphone invalide.',
            'email' => 'Adresse e-mail invalide.',
            'max' => 'Ce champ est trop long.',
        ]);

        $data['anonyme'] = $request->boolean('anonyme');
        if ($data['anonyme']) {
            // Anonyme : aucune information personnelle n'est conservée
            $data['nom'] = $data['telephone'] = $data['email'] = null;
        }

        $plainte = Plainte::create($data + ['reference' => Plainte::genererReference()]);

        return redirect()->route('public.plainte.merci')->with([
            'reference' => $plainte->reference,
            'contactable' => $plainte->peut_etre_contacte,
        ]);
    }

    /**
     * Confirmation d'envoi.
     */
    public function merci(): View|RedirectResponse
    {
        if (! session('reference')) {
            return redirect()->route('public.plainte');
        }

        return view('public.plainte-merci', [
            'reference' => session('reference'),
            'contactable' => session('contactable'),
        ]);
    }
}
