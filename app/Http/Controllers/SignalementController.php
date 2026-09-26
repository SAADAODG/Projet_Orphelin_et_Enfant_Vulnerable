<?php

namespace App\Http\Controllers;

use App\Models\Signalement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SignalementController extends Controller
{
    /**
     * Formulaire public « Signaler un OEV ».
     */
    public function create(): View
    {
        return view('public.signaler', [
            'localites' => config('localites'),
            'vulnerabilites' => Signalement::VULNERABILITES,
            'liens' => Signalement::LIENS,
        ]);
    }

    /**
     * Enregistre le signalement et génère le récépissé.
     */
    public function store(Request $request): RedirectResponse
    {
        $localites = config('localites');

        $data = $request->validate([
            'enfant_nom' => ['required', 'string', 'max:100'],
            'enfant_prenom' => ['required', 'string', 'max:150'],
            'enfant_age' => ['required', 'integer', 'min:0', 'max:17'],
            'vulnerabilite' => ['required', Rule::in(array_keys(Signalement::VULNERABILITES))],
            'vulnerabilite_precision' => ['nullable', 'required_if:vulnerabilite,autre', 'string', 'max:255'],
            'region' => ['required', Rule::in(array_keys($localites))],
            'province' => ['required', Rule::in($localites[$request->input('region')] ?? [])],
            'localite' => ['required', 'string', 'max:150'],
            'declarant_nom' => ['required', 'string', 'max:100'],
            'declarant_prenom' => ['required', 'string', 'max:150'],
            'declarant_telephone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,}$/'],
            'declarant_adresse' => ['required', 'string', 'max:255'],
            'declarant_profession' => ['required', 'string', 'max:150'],
            'declarant_lien' => ['required', Rule::in(array_keys(Signalement::LIENS))],
            'declarant_lien_precision' => ['nullable', 'required_if:declarant_lien,autre', 'string', 'max:255'],
        ], [
            'required' => 'Ce champ est obligatoire.',
            'required_if' => 'Merci de préciser.',
            'in' => 'Veuillez choisir une option valide.',
            'integer' => 'Veuillez saisir un nombre.',
            'enfant_age.min' => "L'âge ne peut pas être négatif.",
            'enfant_age.max' => "L'enfant doit avoir moins de 18 ans.",
            'declarant_telephone.regex' => 'Numéro de téléphone invalide.',
            'max' => 'Ce champ est trop long.',
        ]);

        if ($data['vulnerabilite'] !== 'autre') {
            $data['vulnerabilite_precision'] = null;
        }
        if ($data['declarant_lien'] !== 'autre') {
            $data['declarant_lien_precision'] = null;
        }

        $signalement = Signalement::create($data + ['recepisse' => Signalement::genererRecepisse()]);

        return redirect()->route('public.signaler.merci')->with('recepisse', $signalement->recepisse);
    }

    /**
     * Page de confirmation affichant le récépissé.
     */
    public function merci(): View|RedirectResponse
    {
        $recepisse = session('recepisse');

        if (! $recepisse) {
            return redirect()->route('public.signaler');
        }

        return view('public.signaler-merci', ['recepisse' => $recepisse]);
    }

    /**
     * Suivi public d'un signalement par son numéro de récépissé.
     */
    public function suivi(Request $request): View
    {
        $recepisse = strtoupper(trim((string) $request->query('recepisse')));
        $signalement = $recepisse !== '' ? Signalement::where('recepisse', $recepisse)->first() : null;

        return view('public.suivi', [
            'recepisse' => $recepisse,
            'signalement' => $signalement,
        ]);
    }
}
