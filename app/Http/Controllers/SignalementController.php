<?php

namespace App\Http\Controllers;

use App\Models\Signalement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
            'vulnerabilitesDescriptions' => Signalement::VULNERABILITES_DESCRIPTIONS,
            'vulnerabilitesIcones' => Signalement::VULNERABILITES_ICONES,
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
            'vulnerabilites' => ['required', 'array', 'min:1'],
            'vulnerabilites.*' => [Rule::in(array_keys(Signalement::VULNERABILITES))],
            'vulnerabilite_precision' => [
                'nullable',
                Rule::requiredIf(in_array('autre', (array) $request->input('vulnerabilites', []), true)),
                'string',
                'max:255',
            ],
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
            'vulnerabilites.required' => "Choisissez au moins une situation.",
            'vulnerabilite_precision.required' => 'Merci de préciser la situation.',
            'in' => 'Veuillez choisir une option valide.',
            'integer' => 'Veuillez saisir un nombre.',
            'enfant_age.min' => "L'âge ne peut pas être négatif.",
            'enfant_age.max' => "L'enfant doit avoir moins de 18 ans.",
            'declarant_telephone.regex' => 'Numéro de téléphone invalide.',
            'max' => 'Ce champ est trop long.',
        ]);

        // Plusieurs situations possibles : on les enregistre séparées par des virgules
        $data['vulnerabilite'] = implode(',', array_unique($data['vulnerabilites']));
        unset($data['vulnerabilites']);
        if (! str_contains($data['vulnerabilite'], 'autre')) {
            $data['vulnerabilite_precision'] = null;
        }
        if ($data['declarant_lien'] !== 'autre') {
            $data['declarant_lien_precision'] = null;
        }

        $signalement = Signalement::create($data + ['recepisse' => Signalement::genererRecepisse()]);

        // Seule la personne qui vient d'envoyer le signalement peut télécharger son récépissé PDF
        $request->session()->push('recepisses_autorises', $signalement->recepisse);

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
     * Téléchargement du récépissé PDF (réservé à la session qui a créé le signalement :
     * il contient des données personnelles).
     */
    public function recepisse(Request $request, string $recepisse): Response
    {
        abort_unless(in_array($recepisse, $request->session()->get('recepisses_autorises', []), true), 403);

        $signalement = Signalement::where('recepisse', $recepisse)->firstOrFail();

        return Pdf::loadView('pdf.recepisse', ['signalement' => $signalement])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->download('recepisse-'.$signalement->recepisse.'.pdf');
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
