<?php

namespace App\Http\Controllers;

use App\Models\Oev;
use App\Models\OevDocument;
use App\Models\Region;
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
        'exists' => 'La valeur choisie pour « :attribute » est invalide.',
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
        'region_id' => 'région',
        'province_id' => 'province',
        'commune_id' => 'commune',
        'nom_structure_rib' => 'nom de la structure',
    ];

    /** DP — « Constituer dossier enfant » : tous les dossiers, quel que soit leur état. */
    public function index(Request $request): View
    {
        return $this->lister($request, 'oevs.index', array_keys(Oev::ETATS), null);
    }

    /** DR — « Validation des dossiers » : dossiers soumis ou revenus du central (complément), et ceux déjà traités. */
    public function validation(Request $request): View
    {
        return $this->lister($request, 'oevs.validation', [Oev::ETAT_SOUMIS, Oev::ETAT_COMPLEMENT, Oev::ETAT_VALIDE, Oev::ETAT_NON_CONFORME], Oev::ETAT_SOUMIS)
            // Information pour le DR : dossiers encore chez les DP (non visibles tant qu'ils ne sont pas soumis)
            ->with('enConstitution', Oev::etat(Oev::ETAT_BROUILLON)->count());
    }

    /** Niveau central — « Intégration des OEV » : dossiers validés à intégrer, compléments en attente, OEV intégrés. */
    public function integration(Request $request): View
    {
        return $this->lister($request, 'oevs.integration', [Oev::ETAT_VALIDE, Oev::ETAT_COMPLEMENT, Oev::ETAT_INTEGRE], Oev::ETAT_VALIDE);
    }

    /** « Liste des OEV » : uniquement les enfants intégrés (devenus OEV), visibles de tous les niveaux. */
    public function liste(Request $request): View
    {
        $statut = $request->query('statut');
        $statut = array_key_exists((string) $statut, Oev::STATUTS) ? $statut : null;
        $recherche = trim((string) $request->query('q'));

        $oevs = Oev::etat(Oev::ETAT_INTEGRE)
            ->with(['region', 'province', 'commune'])
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    foreach (['code', 'nom', 'prenom', 'nom_tuteur', 'prenom_tuteur', 'etablissement_actuel'] as $champ) {
                        $q->orWhere($champ, 'like', "%{$recherche}%");
                    }
                    $q->ouLocaliteContient("%{$recherche}%");
                });
            })
            ->orderByDesc('integre_at')
            ->paginate(15)
            ->withQueryString();

        $parStatut = Oev::etat(Oev::ETAT_INTEGRE)
            ->selectRaw('statut, count(*) as total')->groupBy('statut')
            ->pluck('total', 'statut');

        return view('oevs.liste', [
            'oevs' => $oevs,
            'compteurs' => collect(Oev::STATUTS)->mapWithKeys(fn ($l, $s) => [$s => (int) ($parStatut[$s] ?? 0)]),
            'filtres' => compact('statut', 'recherche'),
        ]);
    }

    public function create(): View
    {
        return view('oevs.form', ['oev' => new Oev(), 'documents' => collect(), 'localites' => Region::arborescence()]);
    }

    public function store(Request $request)
    {
        $validated = $this->valider($request);

        $oev = DB::transaction(function () use ($request, $validated) {
            $oev = Oev::create($validated + [
                'numero_dossier' => Oev::genererNumeroDossier(),
                'statut_dossier' => Oev::ETAT_BROUILLON,
                'created_by' => $request->user()->id,
            ]);
            $this->enregistrerDocuments($request, $oev);

            return $oev;
        });

        return $this->apresEnregistrement($request, $oev, "Dossier {$oev->numero_dossier} enregistré.");
    }

    public function show(Request $request, Oev $oev): View
    {
        // Un dossier en cours de constitution n'est visible que du DP (le DR le voit une fois soumis).
        abort_if($oev->statut_dossier === Oev::ETAT_BROUILLON && ! $request->user()->can('constituer dossiers'), 403);

        $oev->load(['documents.auteur', 'createur', 'soumetteur', 'verificateur', 'integrateur', 'demandeurComplement', 'region', 'province', 'commune']);

        return view('oevs.show', ['oev' => $oev, 'documents' => $oev->documents->keyBy('type')]);
    }

    public function edit(Request $request, Oev $oev)
    {
        if ($refus = $this->refuserSiNonModifiable($request, $oev)) {
            return $refus;
        }

        return view('oevs.form', ['oev' => $oev, 'documents' => $oev->documents()->get()->keyBy('type'), 'localites' => Region::arborescence()]);
    }

    public function update(Request $request, Oev $oev)
    {
        if ($refus = $this->refuserSiNonModifiable($request, $oev)) {
            return $refus;
        }
        $validated = $this->valider($request, $oev);

        DB::transaction(function () use ($request, $oev, $validated) {
            $oev->update($validated);
            $this->enregistrerDocuments($request, $oev);
        });

        if ($oev->statut_dossier === Oev::ETAT_COMPLEMENT) {
            return redirect()->route('oevs.show', $oev)->with('success', 'Dossier complété. Vous pouvez maintenant le valider et le renvoyer au niveau central.');
        }

        return $this->apresEnregistrement($request, $oev, 'Dossier mis à jour.');
    }

    /**
     * Après enregistrement par le DP : soumission immédiate au DR si demandée (bouton « Enregistrer et soumettre »)
     * et possible, sinon rappel de l'étape suivante.
     */
    private function apresEnregistrement(Request $request, Oev $oev, string $message)
    {
        $oev->loadCount('documents');
        $redirection = redirect()->route('oevs.show', $oev);

        if ($request->boolean('soumettre')) {
            if ($oev->estComplet()) {
                $this->transmettreAuDr($request, $oev);

                return $redirection->with('success', "{$message} Il a été soumis au DR pour vérification.");
            }

            return $redirection->with('success', $message)->withErrors([
                'circuit' => 'Le dossier n’a pas été soumis au DR : il manque ' . (count(Oev::DOCUMENTS) - $oev->documents_count) . ' pièce(s).',
            ]);
        }

        return $redirection->with('success', $oev->estComplet()
            ? "{$message} Le dossier est complet : cliquez sur « Soumettre au DR » pour le transmettre."
            : "{$message} Complétez les pièces puis soumettez-le au DR.");
    }

    private function transmettreAuDr(Request $request, Oev $oev): void
    {
        $oev->update([
            'statut_dossier' => Oev::ETAT_SOUMIS,
            'soumis_at' => now(),
            'soumis_par' => $request->user()->id,
        ]);
    }

    public function destroy(Oev $oev)
    {
        if ($refus = $this->refuserSiVerrouille($oev)) {
            return $refus;
        }
        $oev->delete();

        return redirect()->route('oevs.index')->with('success', "Dossier {$oev->numero_dossier} supprimé.");
    }

    /** DP : transmet le dossier complet au DR pour vérification. */
    public function soumettre(Request $request, Oev $oev)
    {
        if ($refus = $this->refuserSiVerrouille($oev)) {
            return $refus;
        }
        if (! $oev->estComplet()) {
            return back()->withErrors(['circuit' => 'Le dossier doit comporter les ' . count(Oev::DOCUMENTS) . ' pièces avant d’être soumis au DR.']);
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

    /**
     * Liste commune aux trois écrans du circuit. $etats limite les dossiers visibles ;
     * $etatParDefaut est l'onglet ouvert par défaut (null = tous les états).
     */
    private function lister(Request $request, string $vue, array $etats, ?string $etatParDefaut): View
    {
        $etat = $request->query('etat', $etatParDefaut);
        $etat = in_array($etat, $etats, true) ? $etat : null;
        $statut = $request->query('statut');
        $recherche = trim((string) $request->query('q'));

        $oevs = Oev::withCount('documents')
            ->etat(...($etat ? [$etat] : $etats))
            ->when(array_key_exists((string) $statut, Oev::STATUTS), fn ($q) => $q->where('statut', $statut))
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    foreach (['code', 'numero_dossier', 'nom', 'prenom', 'nom_tuteur', 'prenom_tuteur', 'etablissement_actuel'] as $champ) {
                        $q->orWhere($champ, 'like', "%{$recherche}%");
                    }
                    $q->ouLocaliteContient("%{$recherche}%");
                });
            })
            ->latest(match ($etat) {
                Oev::ETAT_SOUMIS => 'soumis_at',
                Oev::ETAT_COMPLEMENT => 'complement_at',
                Oev::ETAT_INTEGRE => 'integre_at',
                default => 'updated_at',
            })
            ->paginate(15)
            ->withQueryString();

        $compteurs = Oev::query()->etat(...$etats)
            ->selectRaw('statut_dossier, count(*) as total')->groupBy('statut_dossier')
            ->pluck('total', 'statut_dossier');

        return view($vue, [
            'oevs' => $oevs,
            'etats' => $etats,
            'compteurs' => collect($etats)->mapWithKeys(fn ($e) => [$e => (int) ($compteurs[$e] ?? 0)]),
            'filtres' => compact('etat', 'statut', 'recherche'),
        ]);
    }

    /** Un dossier soumis, validé ou intégré n'est plus modifiable par le DP. */
    private function refuserSiVerrouille(Oev $oev)
    {
        if ($oev->estModifiable()) {
            return null;
        }

        return redirect()->route('oevs.show', $oev)
            ->withErrors(['circuit' => "Ce dossier est « {$oev->libelleEtat()} » : il ne peut plus être modifié."]);
    }

    /** Modification des informations et des pièces : DP en constitution, DR sur demande de complément du central. */
    private function refuserSiNonModifiable(Request $request, Oev $oev)
    {
        $utilisateur = $request->user();
        if ($oev->peutEtreModifiePar($utilisateur)) {
            return null;
        }
        // Hors DP, seul le DR peut intervenir, et uniquement sur un complément demandé par le central.
        abort_unless(
            $utilisateur->can('constituer dossiers')
            || ($utilisateur->can('valider dossiers') && $oev->statut_dossier !== Oev::ETAT_BROUILLON),
            403,
        );

        return redirect()->route('oevs.show', $oev)
            ->withErrors(['circuit' => "Ce dossier est « {$oev->libelleEtat()} » : vous ne pouvez pas le modifier à cette étape."]);
    }

    public function showDocument(Oev $oev, OevDocument $document): StreamedResponse
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disque */
        $disque = Storage::disk('local');
        abort_unless($document->oev_id === $oev->id && $disque->exists($document->chemin), 404);

        return $disque->response($document->chemin, $document->nom_original);
    }

    public function destroyDocument(Request $request, Oev $oev, OevDocument $document)
    {
        abort_unless($document->oev_id === $oev->id, 404);
        if ($refus = $this->refuserSiNonModifiable($request, $oev)) {
            return $refus;
        }

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

            ...Oev::reglesLocalite($request),

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
