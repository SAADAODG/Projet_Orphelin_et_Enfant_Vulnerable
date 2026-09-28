<?php

namespace App\Http\Controllers;

use App\Models\Oev;
use App\Models\OevDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OevController extends Controller
{
    /** Messages de validation en français, propres au formulaire OEV (la langue de l'application n'est pas modifiée). */
    private const MESSAGES = [
        'required' => 'Le champ « :attribute » est obligatoire.',
        'string' => 'Le champ « :attribute » doit être un texte.',
        'max.string' => 'Le champ « :attribute » ne doit pas dépasser :max caractères.',
        'max.numeric' => 'Le champ « :attribute » ne doit pas être supérieur à :max.',
        'max.file' => 'Le fichier « :attribute » ne doit pas dépasser :max Ko.',
        'min.numeric' => 'Le champ « :attribute » doit être supérieur ou égal à :min.',
        'integer' => 'Le champ « :attribute » doit être un nombre entier.',
        'numeric' => 'Le champ « :attribute » doit être un nombre.',
        'boolean' => 'Le champ « :attribute » est invalide.',
        'in' => 'La valeur choisie pour « :attribute » est invalide.',
        'date' => 'Le champ « :attribute » n’est pas une date valide.',
        'regex' => 'Le format du champ « :attribute » est invalide.',
        'file' => '« :attribute » doit être un fichier.',
        'image' => '« :attribute » doit être une image.',
        'mimes' => '« :attribute » doit être au format : :values.',
        'uploaded' => '« :attribute » n’a pas pu être téléversé.',
    ];

    private const ATTRIBUTS = [
        'nom' => 'nom de l’enfant',
        'prenom' => 'prénom(s) de l’enfant',
        'sexe' => 'sexe',
        'date_naissance' => 'date de naissance',
        'statut' => 'statut',
        'handicap' => 'situation de handicap',
        'nature_handicap' => 'nature du handicap',
        'systeme_educatif' => 'système éducatif',
        'nom_tuteur' => 'nom du parent ou tuteur',
        'prenom_tuteur' => 'prénom(s) du parent ou tuteur',
        'contact_tuteur' => 'téléphone du parent ou tuteur',
        'etablissement_precedent' => 'établissement de l’année précédente',
        'moyenne_annuelle' => 'moyenne annuelle',
        'appreciation' => 'appréciation',
        'etablissement_actuel' => 'établissement de l’année en cours',
        'type_etablissement' => 'public ou privé',
        'classe' => 'classe',
        'frais_scolarite' => 'frais de scolarité',
        'region' => 'région',
        'province' => 'province',
        'commune' => 'commune',
        'nom_structure_rib' => 'nom de la structure',
    ];

    public function index(Request $request): View
    {
        $dossier = $request->query('dossier');
        $statut = $request->query('statut');
        $recherche = trim((string) $request->query('q'));

        $oevs = Oev::withCount('documents')
            ->when($dossier === 'complet', fn ($q) => $q->dossierComplet())
            ->when($dossier === 'incomplet', fn ($q) => $q->dossierIncomplet())
            ->when($dossier === 'aucun', fn ($q) => $q->sansDossier())
            ->when(array_key_exists((string) $statut, Oev::STATUTS), fn ($q) => $q->where('statut', $statut))
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    foreach (['code', 'nom', 'prenom', 'nom_tuteur', 'prenom_tuteur', 'region', 'province', 'commune', 'etablissement_actuel'] as $champ) {
                        $q->orWhere($champ, 'like', "%{$recherche}%");
                    }
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('oevs.index', [
            'oevs' => $oevs,
            'stats' => [
                'total' => Oev::count(),
                'complet' => Oev::dossierComplet()->count(),
                'incomplet' => Oev::dossierIncomplet()->count(),
                'aucun' => Oev::sansDossier()->count(),
            ],
            'filtres' => compact('dossier', 'statut', 'recherche'),
        ]);
    }

    public function create(): View
    {
        return view('oevs.form', ['oev' => new Oev(), 'documents' => collect()]);
    }

    public function store(Request $request)
    {
        $validated = $this->valider($request);

        $oev = DB::transaction(function () use ($request, $validated) {
            $oev = Oev::create($validated + [
                'code' => Oev::genererCode(),
                'created_by' => $request->user()->id,
            ]);
            $this->enregistrerDocuments($request, $oev);

            return $oev;
        });

        return redirect()->route('oevs.show', $oev)->with('success', "OEV {$oev->code} enregistré avec succès.");
    }

    public function show(Oev $oev): View
    {
        $oev->load(['documents.auteur', 'createur']);

        return view('oevs.show', ['oev' => $oev, 'documents' => $oev->documents->keyBy('type')]);
    }

    public function edit(Oev $oev): View
    {
        return view('oevs.form', ['oev' => $oev, 'documents' => $oev->documents()->get()->keyBy('type')]);
    }

    public function update(Request $request, Oev $oev)
    {
        $validated = $this->valider($request, $oev);

        DB::transaction(function () use ($request, $oev, $validated) {
            $oev->update($validated);
            $this->enregistrerDocuments($request, $oev);
        });

        return redirect()->route('oevs.show', $oev)->with('success', 'OEV mis à jour.');
    }

    public function destroy(Oev $oev)
    {
        $oev->delete();

        return redirect()->route('oevs.index')->with('success', "OEV {$oev->code} supprimé.");
    }

    public function showDocument(Oev $oev, OevDocument $document): StreamedResponse
    {
        abort_unless($document->oev_id === $oev->id && Storage::disk('local')->exists($document->chemin), 404);

        return Storage::disk('local')->response($document->chemin, $document->nom_original);
    }

    public function destroyDocument(Oev $oev, OevDocument $document)
    {
        abort_unless($document->oev_id === $oev->id, 404);

        Storage::disk('local')->delete($document->chemin);
        $document->delete();

        return back()->with('success', "{$document->libelle()} retiré(e) du dossier.");
    }

    private function valider(Request $request, ?Oev $oev = null): array
    {
        $ribExistant = $oev?->documents()->where('type', 'rib')->exists() ?? false;

        // Le numéro est saisi sans indicatif (8 chiffres) ; on retire espaces et +226 éventuels avant validation.
        if ($request->filled('contact_tuteur')) {
            $request->merge(['contact_tuteur' => Oev::numeroLocal($request->input('contact_tuteur'))]);
        }

        $regles = [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'sexe' => ['required', Rule::in(array_keys(Oev::SEXES))],
            'date_naissance' => ['required', 'date', 'before_or_equal:today', 'after:' . now()->subYears(25)->toDateString()],
            'statut' => ['required', Rule::in(array_keys(Oev::STATUTS))],
            'handicap' => ['required', 'boolean'],
            'nature_handicap' => ['nullable', 'required_if:handicap,1', 'string', 'max:255'],
            'systeme_educatif' => ['required', Rule::in(array_keys(Oev::SYSTEMES_EDUCATIFS))],

            'nom_tuteur' => ['required', 'string', 'max:100'],
            'prenom_tuteur' => ['required', 'string', 'max:150'],
            'contact_tuteur' => ['required', 'regex:/^\d{8}$/'],

            'etablissement_precedent' => ['nullable', 'string', 'max:255'],
            'moyenne_annuelle' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'appreciation' => ['nullable', Rule::in(array_keys(Oev::APPRECIATIONS))],

            'etablissement_actuel' => ['required', 'string', 'max:255'],
            'type_etablissement' => ['required', Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            'classe' => ['required', 'string', 'max:50'],
            'frais_scolarite' => ['required', 'integer', 'min:0'],

            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'commune' => ['required', 'string', 'max:100'],

            'nom_structure_rib' => [($request->hasFile('rib') || $ribExistant) ? 'required' : 'nullable', 'string', 'max:255'],
        ];

        $messagesFichiers = [];
        foreach (array_keys(Oev::DOCUMENTS) as $type) {
            $max = Oev::tailleMaxFichierKo($type);
            $regles[$type] = $type === 'photo'
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png', "max:{$max}"]
                : ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$max}"];

            // Fichier refusé par PHP lui-même (au-delà de upload_max_filesize)
            $messagesFichiers["{$type}.uploaded"] = Oev::DOCUMENTS[$type] . ' : fichier trop volumineux (maximum ' . round($max / 1024, 1) . ' Mo). Veuillez le sélectionner à nouveau.';
            $messagesFichiers["{$type}.max"] = Oev::DOCUMENTS[$type] . ' : fichier trop volumineux (maximum ' . round($max / 1024, 1) . ' Mo).';
        }

        $validated = $request->validate($regles, $messagesFichiers + [
            'nature_handicap.required_if' => 'Précisez la nature du handicap.',
            'nom_structure_rib.required' => 'Précisez le nom de la structure titulaire du RIB.',
            'date_naissance.before_or_equal' => 'La date de naissance ne peut pas être dans le futur.',
            'date_naissance.after' => 'L’enfant doit avoir moins de 25 ans.',
            'contact_tuteur.regex' => 'Le numéro de téléphone doit comporter 8 chiffres (ex : 70 12 34 56).',
        ] + self::MESSAGES, Oev::DOCUMENTS + self::ATTRIBUTS);

        if (! $validated['handicap']) {
            $validated['nature_handicap'] = null;
        }
        $validated['contact_tuteur'] = Oev::formaterTelephone($validated['contact_tuteur']);

        return collect($validated)->except(array_keys(Oev::DOCUMENTS))->all();
    }

    private function enregistrerDocuments(Request $request, Oev $oev): void
    {
        foreach (array_keys(Oev::DOCUMENTS) as $type) {
            if (! $request->hasFile($type)) {
                continue;
            }

            $fichier = $request->file($type);
            $ancien = $oev->documents()->where('type', $type)->value('chemin');

            $oev->documents()->updateOrCreate(['type' => $type], [
                'chemin' => $fichier->store("oevs/{$oev->id}", 'local'),
                'nom_original' => $fichier->getClientOriginalName(),
                'mime_type' => $fichier->getMimeType(),
                'taille' => $fichier->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);

            if ($ancien) {
                Storage::disk('local')->delete($ancien);
            }
        }
    }
}
