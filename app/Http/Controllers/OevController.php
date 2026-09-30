<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Oev;
use App\Models\OevDocument;
use App\Models\Region;
use App\Models\User;
use App\Support\Perimetre;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OevController extends Controller implements HasMiddleware
{
    /** Un dossier hors de la zone de l'utilisateur, ou pas encore arrivé à l'étape où il intervient, n'est pas accessible. */
    public static function middleware(): array
    {
        return [
            function (Request $request, Closure $next) {
                $oev = $request->route('oev');
                abort_if($oev instanceof Oev && ! $oev->estVisiblePar($request->user()), 403);

                return $next($request);
            },
        ];
    }

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
        'village_id' => 'village / secteur',
        'nom_structure_rib' => 'nom de la structure',
        'date_naissance_estimee' => 'date estimée',
        'lieu_naissance' => 'lieu de naissance',
        'nationalite' => 'nationalité',
        'a_acte_naissance' => 'acte de naissance',
        'groupe_population' => 'groupe de population',
        'quartier' => 'quartier / précision de l’adresse',
        'lieu_provenance' => 'lieu de provenance',
        'tuteur_sexe' => 'sexe du tuteur',
        'tuteur_lien' => 'lien de parenté du tuteur',
        'tuteur_cnib' => 'numéro CNIB du tuteur',
        'tuteur_pret_continuer' => 'disponibilité du tuteur',
        'tuteur_raison_arret' => 'raison pour laquelle le tuteur ne peut pas continuer',
        'lieu_naissance_commune_id' => 'lieu de naissance',
        'numero_acte_naissance' => 'numéro de l’acte de naissance',
        'maladie_nom' => 'nom de la maladie',
        'suivi_clinique' => 'suivi clinique',
        'classe_precedente' => 'classe de l’année précédente',
        'performance_scolaire' => 'performances scolaires',
        'performance_difficultes' => 'explication des difficultés scolaires',
        'formation_duree_mois' => 'durée de la formation',
        'formation_duree_recue_mois' => 'durée de formation déjà reçue',
        'mere_nom' => 'nom de la mère',
        'mere_prenoms' => 'prénoms de la mère',
        'mere_vivante' => 'mère vivante',
        'mere_date_deces' => 'date du décès de la mère',
        'mere_deces_confirme' => 'décès de la mère confirmé',
        'pere_nom' => 'nom du père',
        'pere_prenoms' => 'prénoms du père',
        'pere_vivant' => 'père vivant',
        'pere_date_deces' => 'date du décès du père',
        'pere_deces_confirme' => 'décès du père confirmé',
        'lieu_de_vie' => 'lieu de vie de l’enfant',
        'lieu_de_vie_precision' => 'précision sur le lieu de vie',
        'vulnerabilites' => 'situations de vulnérabilité',
        'vulnerabilite_precision' => 'autre situation de vulnérabilité',
        'types_handicap' => 'type de handicap',
        'maladie_chronique' => 'maladie chronique',
        'source_revenu' => 'source de revenu',
        'niveau_revenu' => 'niveau de revenu',
        'logement' => 'logement',
        'date_identification' => 'date d’identification',
        'identifie_par' => 'personne ou institution ayant identifié l’enfant',
        'niveau_priorite' => 'niveau de priorité',
        'situation_scolaire' => 'situation scolaire',
        'niveau_etude' => 'niveau d’étude',
        'raison_non_scolarisation' => 'raison de la non-scolarisation',
        'raison_non_scolarisation_precision' => 'précision sur la raison',
        'formation_professionnelle' => 'formation professionnelle',
        'formation_etat' => 'état de la formation',
        'formation_filiere' => 'filière de formation',
        'formation_type_centre' => 'type de centre de formation',
    ];

    /** DP — « Constituer dossier enfant » : tous les dossiers, quel que soit leur état. */
    public function index(Request $request): View
    {
        return $this->lister($request, 'oevs.index', array_keys(Oev::ETATS), null);
    }

    /** DR — « Validation des dossiers » : dossiers soumis ou revenus du central (complément), et ceux déjà traités. */
    public function validation(Request $request): View
    {
        return $this->lister($request, 'oevs.validation', [Oev::ETAT_SOUMIS, Oev::ETAT_COMPLEMENT, Oev::ETAT_VALIDE, Oev::ETAT_NON_CONFORME, Oev::ETAT_REJETE], Oev::ETAT_SOUMIS)
            // Information pour le DR : dossiers encore chez les DP (non visibles tant qu'ils ne sont pas soumis)
            ->with('enConstitution', Oev::etat(Oev::ETAT_BROUILLON)->dansLePerimetreDe($request->user())->count());
    }

    /** Niveau central — « Intégration des OEV » : dossiers validés à intégrer, compléments en attente, OEV intégrés. */
    public function integration(Request $request): View
    {
        return $this->lister($request, 'oevs.integration', [Oev::ETAT_VALIDE, Oev::ETAT_COMPLEMENT, Oev::ETAT_INTEGRE, Oev::ETAT_REJETE], Oev::ETAT_VALIDE);
    }

    /** « Liste des OEV » : uniquement les enfants intégrés (devenus OEV), visibles de tous les niveaux. */
    public function liste(Request $request): View
    {
        $statut = $request->query('statut');
        $statut = array_key_exists((string) $statut, Oev::STATUTS) ? $statut : null;
        $recherche = trim((string) $request->query('q'));

        $oevs = Oev::visiblesPar($request->user())->etat(Oev::ETAT_INTEGRE)
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

        $parStatut = Oev::visiblesPar($request->user())->etat(Oev::ETAT_INTEGRE)
            ->selectRaw('statut, count(*) as total')->groupBy('statut')
            ->pluck('total', 'statut');

        return view('oevs.liste', [
            'oevs' => $oevs,
            'compteurs' => collect(Oev::STATUTS)->mapWithKeys(fn ($l, $s) => [$s => (int) ($parStatut[$s] ?? 0)]),
            'filtres' => compact('statut', 'recherche'),
        ]);
    }

    public function create(Request $request): View
    {
        // Le DP ne constitue que des dossiers de sa province : elle est proposée d'office
        $perimetre = Perimetre::pour($request->user());
        $oev = new Oev(['region_id' => $perimetre->region?->id, 'province_id' => $perimetre->province?->id]);

        return view('oevs.form', ['oev' => $oev, 'documents' => collect(), 'localites' => Region::arborescence(avecVillages: true)]);
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

        $oev->load(['documents.auteur', 'createur', 'soumetteur', 'verificateur', 'integrateur', 'demandeurComplement', 'auteurRejet', 'region', 'province', 'commune', 'village', 'lieuNaissanceCommune']);

        return view('oevs.show', ['oev' => $oev, 'documents' => $oev->documents->keyBy('type')]);
    }

    public function edit(Request $request, Oev $oev)
    {
        if ($refus = $this->refuserSiNonModifiable($request, $oev)) {
            return $refus;
        }

        return view('oevs.form', ['oev' => $oev, 'documents' => $oev->documents()->get()->keyBy('type'), 'localites' => Region::arborescence(avecVillages: true)]);
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
        $oev->load('documents');
        $redirection = redirect()->route('oevs.show', $oev);

        if ($request->boolean('soumettre')) {
            if ($oev->estComplet()) {
                $this->transmettreAuDr($request, $oev);

                return $redirection->with('success', "{$message} Il a été soumis au DR pour vérification.");
            }

            return $redirection->with('success', $message)->withErrors([
                'circuit' => 'Le dossier n’a pas été soumis au DR : il manque ' . $this->piecesManquantes($oev) . '.',
            ]);
        }

        return $redirection->with('success', $oev->estComplet()
            ? "{$message} Le dossier est complet : cliquez sur « Soumettre au DR » pour le transmettre."
            : "{$message} Complétez les pièces puis soumettez-le au DR.");
    }

    /** Liste lisible des pièces exigées non encore fournies, ex. « l’acte de naissance, la photo ». */
    private function piecesManquantes(Oev $oev): string
    {
        $manquantes = array_diff_key($oev->piecesRequises(), array_flip($oev->piecesFournies()));

        return mb_strtolower(implode(', ', $manquantes));
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
            return back()->withErrors(['circuit' => 'Le dossier ne peut pas être soumis au DR : il manque ' . $this->piecesManquantes($oev) . '.']);
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

    /**
     * Rejet définitif, avec motif : par le DR (dossier soumis ou en complément) ou par le central (dossier validé).
     * Le dossier est clos : l'enfant ne devient pas OEV et le dossier n'est plus modifiable.
     */
    public function rejeter(Request $request, Oev $oev)
    {
        $niveau = $oev->niveauRejetPour($request->user());
        if (! $niveau) {
            abort_unless($request->user()->canAny(['valider dossiers', 'intégrer OEV']), 403);

            return back()->withErrors(['circuit' => "Ce dossier est « {$oev->libelleEtat()} » : il ne peut pas être rejeté à cette étape."]);
        }
        $validated = $request->validate(
            ['motif_rejet' => ['required', 'string', 'max:2000']],
            ['motif_rejet.required' => 'Indiquez le motif du rejet.'],
        );

        $oev->update([
            'statut_dossier' => Oev::ETAT_REJETE,
            'motif_rejet' => $validated['motif_rejet'],
            'rejete_niveau' => $niveau,
            'rejete_at' => now(),
            'rejete_par' => $request->user()->id,
        ]);

        return redirect()->route($niveau === Oev::REJET_DR ? 'oevs.validation' : 'oevs.integration')
            ->with('success', "Dossier {$oev->numero_dossier} rejeté.");
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

        $oevs = Oev::with('documents:id,oev_id,type')
            ->visiblesPar($request->user())
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
                Oev::ETAT_REJETE => 'rejete_at',
                default => 'updated_at',
            })
            ->paginate(15)
            ->withQueryString();

        $compteurs = Oev::query()->visiblesPar($request->user())->etat(...$etats)
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

        $choix = fn (array $liste) => ['nullable', Rule::in(array_keys($liste))];
        $entree = fn (string $champ) => $request->input($champ);

        // Conditions entre champs (les mêmes que l'affichage conditionnel du formulaire)
        $situation = $entree('situation_scolaire');
        $estScolarise = $situation === 'scolarise';
        $aEteScolarise = in_array($situation, ['scolarise', 'descolarise'], true);
        $nonScolarise = in_array($situation, ['non_scolarise', 'descolarise'], true);
        $vulnerabilitesSaisies = (array) $entree('vulnerabilites');
        $scolariseRequis = Rule::requiredIf($estScolarise);

        // Parent(s) qui s'occupent de l'enfant : ils doivent être déclarés vivants, et leurs nom et prénoms
        // (saisis dans la partie « Parents ») sont repris comme ceux du tuteur.
        $parentsTuteurs = Oev::parentsTuteurs($entree('tuteur_lien'));
        $tuteurEstParent = $parentsTuteurs !== [];
        $tuteurVivant = function (string $attribut, $valeur, \Closure $echec) use ($entree): void {
            foreach (Oev::parentsTuteurs($valeur) as $parent) {
                $etat = $entree($parent === 'mere' ? 'mere_vivante' : 'pere_vivant');
                if ($etat === 'non') {
                    $echec($parent === 'mere'
                        ? 'La mère est déclarée décédée : elle ne peut pas être la tutrice de l’enfant.'
                        : 'Le père est déclaré décédé : il ne peut pas être le tuteur de l’enfant.');
                } elseif ($etat !== 'oui') {
                    $echec($parent === 'mere'
                        ? 'On ne sait pas si la mère est vivante : elle ne peut pas être indiquée comme tutrice.'
                        : 'On ne sait pas si le père est vivant : il ne peut pas être indiqué comme tuteur.');
                }
            }
        };

        // Lieu de naissance : une commune du Burkina, ou « autre » (hors du pays / lieu non répertorié) à préciser
        $lieuNaissanceAutre = $entree('lieu_naissance_commune_id') === 'autre';
        $communeDeNaissance = function (string $attribut, $valeur, \Closure $echec): void {
            if ($valeur !== 'autre' && ! Commune::whereKey($valeur)->exists()) {
                $echec('Choisissez le lieu de naissance dans la liste.');
            }
        };

        // Moyenne : sur 10 au préscolaire et au primaire, sur 20 ensuite (selon la classe de l'année précédente)
        $bareme = Oev::baremeMoyenne($entree('classe_precedente'));
        $moyenneDansLeBareme = function (string $attribut, $valeur, \Closure $echec) use ($bareme, $entree): void {
            if (is_numeric($valeur) && $valeur > $bareme) {
                $classe = Oev::toutesLesClasses()[$entree('classe_precedente')] ?? null;
                $echec("La moyenne doit être comprise entre 0 et {$bareme}" . ($classe ? " (notation sur {$bareme} en {$classe})." : '.'));
            }
        };
        // Appréciation cohérente avec la classe actuelle : un redoublant reste dans la même classe, un admis en change
        $appreciationCoherente = function (string $attribut, $valeur, \Closure $echec) use ($entree, $estScolarise): void {
            $actuelle = $entree('classe');
            $precedente = $entree('classe_precedente');
            if (! $estScolarise || ! $actuelle || ! $precedente) {
                return;
            }
            if ($valeur === 'redouble' && $actuelle !== $precedente) {
                $echec('L’enfant a redoublé : sa classe actuelle devrait être la même que l’année précédente.');
            }
            if ($valeur === 'admis' && $actuelle === $precedente) {
                $echec('L’enfant a été admis : sa classe actuelle ne peut pas être la même que l’année précédente.');
            }
        };
        // Formation : la durée déjà reçue ne peut pas dépasser la durée prévue
        $dureeRecueCoherente = function (string $attribut, $valeur, \Closure $echec) use ($entree): void {
            $totale = $entree('formation_duree_mois');
            if (is_numeric($valeur) && is_numeric($totale) && (int) $valeur > (int) $totale) {
                $echec('La durée déjà reçue ne peut pas dépasser la durée de la formation.');
            }
        };
        // Le père peut être décédé pendant la grossesse (jusqu'à 10 mois avant la naissance), pas avant
        $decesPereCoherent = function (string $attribut, $valeur, \Closure $echec) use ($entree): void {
            $naissance = strtotime((string) $entree('date_naissance'));
            if ($naissance && strtotime((string) $valeur) < strtotime('-10 months', $naissance)) {
                $echec('La date du décès du père est incohérente avec la date de naissance de l’enfant.');
            }
        };

        $regles = [
            // Identité
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'sexe' => ['required', Rule::in(array_keys(Oev::SEXES))],
            'date_naissance' => ['required', 'date', 'before_or_equal:today', 'after:' . now()->subYears(25)->toDateString()],
            'date_naissance_estimee' => ['nullable', 'boolean'],
            'lieu_naissance_commune_id' => ['nullable', $communeDeNaissance],
            'lieu_naissance' => ['nullable', Rule::requiredIf($lieuNaissanceAutre), 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'a_acte_naissance' => ['required', 'boolean'],
            'numero_acte_naissance' => ['nullable', 'required_if:a_acte_naissance,1', 'string', 'max:50'],
            'groupe_population' => $choix(Oev::GROUPES_POPULATION),

            // Adresse : région, province et commune choisies dans le référentiel des localités
            ...Oev::reglesLocalite($request),
            // Village facultatif, mais forcément dans la commune choisie
            'village_id' => ['nullable', 'integer', Rule::exists('villages', 'id')->where('commune_id', $request->input('commune_id'))],
            'quartier' => ['nullable', 'string', 'max:150'],
            'lieu_provenance' => ['nullable', 'string', 'max:150'],

            // Parents (situation d'orphelin) : « vivant ? » obligatoire, le statut OEV en est déduit
            // Nom et prénoms obligatoires pour le parent qui s'occupe de l'enfant (repris comme tuteur)
            'mere_nom' => ['nullable', Rule::requiredIf(in_array('mere', $parentsTuteurs, true)), 'string', 'max:100'],
            'mere_prenoms' => ['nullable', Rule::requiredIf(in_array('mere', $parentsTuteurs, true)), 'string', 'max:150'],
            'mere_vivante' => ['required', Rule::in(array_keys(Oev::PARENT_VIVANT))],
            'mere_date_deces' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_naissance'],
            'mere_deces_confirme' => ['nullable', 'boolean'],
            'pere_nom' => ['nullable', Rule::requiredIf(in_array('pere', $parentsTuteurs, true)), 'string', 'max:100'],
            'pere_prenoms' => ['nullable', Rule::requiredIf(in_array('pere', $parentsTuteurs, true)), 'string', 'max:150'],
            'pere_vivant' => ['required', Rule::in(array_keys(Oev::PARENT_VIVANT))],
            'pere_date_deces' => ['nullable', 'date', 'before_or_equal:today', $decesPereCoherent],
            'pere_deces_confirme' => ['nullable', 'boolean'],

            // Tuteur
            'tuteur_lien' => ['required', Rule::in(array_keys(Oev::LIENS_TUTEUR)), $tuteurVivant],
            'tuteur_lien_precision' => ['nullable', 'required_if:tuteur_lien,autre_parent', 'string', 'max:100'],
            // Nom, prénoms et sexe demandés seulement si le tuteur n'est pas un parent (déjà saisi plus haut)
            'nom_tuteur' => ['nullable', Rule::requiredIf(! $tuteurEstParent), 'string', 'max:100'],
            'prenom_tuteur' => ['nullable', Rule::requiredIf(! $tuteurEstParent), 'string', 'max:150'],
            'contact_tuteur' => ['required', 'regex:/^\d{8}$/'],
            'tuteur_sexe' => ['nullable', Rule::requiredIf(! $tuteurEstParent), Rule::in(['M', 'F'])],
            'tuteur_a_cnib' => ['required', 'boolean'],
            'tuteur_cnib' => ['nullable', 'required_if:tuteur_a_cnib,1', 'string', 'max:50'],
            'tuteur_pret_continuer' => ['required', 'boolean'],
            'tuteur_raison_arret' => ['nullable', 'required_if:tuteur_pret_continuer,0', 'string', 'max:255'],

            // Conditions de vie et vulnérabilités
            'lieu_de_vie' => $choix(Oev::LIEUX_DE_VIE),
            'lieu_de_vie_precision' => ['nullable', 'required_if:lieu_de_vie,autre', 'string', 'max:150'],
            'vulnerabilites' => ['nullable', 'array'],
            'vulnerabilites.*' => [Rule::in(array_keys(Oev::VULNERABILITES))],
            'vulnerabilite_precision' => ['nullable', Rule::requiredIf(in_array('autre', $vulnerabilitesSaisies, true)), 'string', 'max:255'],

            // Santé
            'handicap' => ['required', 'boolean'],
            'types_handicap' => ['nullable', 'array', 'required_if:handicap,1'],
            'types_handicap.*' => [Rule::in(array_keys(Oev::TYPES_HANDICAP))],
            'nature_handicap' => ['nullable', 'string', 'max:255'],
            'maladie_chronique' => ['nullable', 'boolean'],
            'maladie_nom' => ['nullable', 'required_if:maladie_chronique,1', 'string', 'max:150'],
            'suivi_clinique' => ['nullable', 'required_if:maladie_chronique,1', 'boolean'],

            // Ménage
            'source_revenu' => $choix(Oev::SOURCES_REVENU),
            'niveau_revenu' => $choix(Oev::NIVEAUX),
            'logement' => $choix(Oev::LOGEMENTS),

            // Identification du cas
            'date_identification' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_naissance'],
            'identifie_par' => $choix(Oev::IDENTIFIE_PAR),
            'niveau_priorite' => $choix(Oev::NIVEAUX),

            // Scolarité
            'situation_scolaire' => ['required', Rule::in(array_keys(Oev::SITUATIONS_SCOLAIRES))],
            'niveau_etude' => ['nullable', Rule::requiredIf($aEteScolarise), Rule::in(array_keys(Oev::NIVEAUX_ETUDE))],
            'raison_non_scolarisation' => ['nullable', Rule::requiredIf($nonScolarise), Rule::in(array_keys(Oev::RAISONS_NON_SCOLARISATION))],
            'raison_non_scolarisation_precision' => ['nullable', Rule::requiredIf($nonScolarise && $entree('raison_non_scolarisation') === 'autre'), 'string', 'max:255'],
            'etablissement_precedent' => ['nullable', 'string', 'max:255'],
            // La classe de l'année précédente fixe le barème de la moyenne (/10 ou /20)
            'classe_precedente' => ['nullable', Rule::requiredIf(filled($entree('moyenne_annuelle'))), Rule::in(array_keys(Oev::toutesLesClasses()))],
            'moyenne_annuelle' => ['nullable', 'numeric', 'min:0', $moyenneDansLeBareme],
            'appreciation' => [...$choix(Oev::APPRECIATIONS), $appreciationCoherente],
            'performance_scolaire' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::PERFORMANCES_SCOLAIRES))],
            'performance_difficultes' => ['nullable', Rule::requiredIf($estScolarise && $entree('performance_scolaire') === 'difficultes'), 'string', 'max:255'],
            'systeme_educatif' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::SYSTEMES_EDUCATIFS))],
            'etablissement_actuel' => ['nullable', $scolariseRequis, 'string', 'max:255'],
            'type_etablissement' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            // La classe doit appartenir au niveau d'étude choisi (ex. pas de « 6e » en primaire)
            'classe' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::classesDuNiveau($entree('niveau_etude'))))],
            'frais_scolarite' => ['nullable', $scolariseRequis, 'integer', 'min:0'],
            'formation_professionnelle' => ['nullable', 'boolean'],
            'formation_etat' => ['nullable', 'required_if:formation_professionnelle,1', Rule::in(array_keys(Oev::ETATS_FORMATION))],
            'formation_filiere' => ['nullable', 'required_if:formation_professionnelle,1', 'string', 'max:150'],
            'formation_type_centre' => ['nullable', 'required_if:formation_professionnelle,1', Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            'formation_duree_mois' => ['nullable', 'required_if:formation_professionnelle,1', 'integer', 'min:1', 'max:120'],
            // Formation achevée : la durée reçue est la durée totale (non demandée)
            'formation_duree_recue_mois' => ['nullable', Rule::requiredIf($entree('formation_professionnelle') === '1' && $entree('formation_etat') === 'en_cours'), 'integer', 'min:0', $dureeRecueCoherente],

            'nom_structure_rib' => [($request->hasFile('rib') || $ribExistant) ? 'required' : 'nullable', 'string', 'max:255'],
        ];

        $messagesFichiers = $this->limiterALaZone($request, $regles);
        foreach (array_keys(Oev::DOCUMENTS) as $type) {
            $max = Oev::tailleMaxFichierKo($type);
            $regles[$type] = $type === 'photo'
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png', "max:{$max}"]
                : ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$max}"];

            // Fichier refusé par PHP lui-même (au-delà de upload_max_filesize)
            $messagesFichiers["{$type}.uploaded"] = Oev::DOCUMENTS[$type] . ' : fichier trop volumineux (maximum ' . round($max / 1024, 1) . ' Mo). Veuillez le sélectionner à nouveau.';
            $messagesFichiers["{$type}.max"] = Oev::DOCUMENTS[$type] . ' : fichier trop volumineux (maximum ' . round($max / 1024, 1) . ' Mo).';
        }

        $obligatoireScolarise = 'Le champ « :attribute » est obligatoire pour un enfant scolarisé.';
        $validated = $request->validate($regles, $messagesFichiers + [
            'types_handicap.required_if' => 'Précisez le type de handicap.',
            'required_if' => 'Le champ « :attribute » est obligatoire avec cette réponse.',
            'systeme_educatif.required' => $obligatoireScolarise,
            'etablissement_actuel.required' => $obligatoireScolarise,
            'type_etablissement.required' => $obligatoireScolarise,
            'classe.required' => $obligatoireScolarise,
            'frais_scolarite.required' => $obligatoireScolarise,
            'classe.in' => 'La classe choisie ne correspond pas au niveau d’étude.',
            'niveau_etude.required' => 'Indiquez le niveau d’étude de l’enfant.',
            'raison_non_scolarisation.required' => 'Indiquez pourquoi l’enfant n’est pas (ou plus) scolarisé.',
            'a_acte_naissance.required' => 'Indiquez si l’enfant a un acte de naissance.',
            'tuteur_a_cnib.required' => 'Indiquez si le tuteur possède une CNIB.',
            'tuteur_cnib.required_if' => 'Saisissez le numéro de CNIB du tuteur.',
            'tuteur_lien.required' => 'Indiquez le lien du tuteur avec l’enfant.',
            'tuteur_lien_precision.required_if' => 'Précisez quel membre de la famille est le tuteur.',
            'mere_date_deces.after_or_equal' => 'La mère ne peut pas être décédée avant la naissance de l’enfant.',
            'date_identification.after_or_equal' => 'La date d’identification ne peut pas précéder la naissance de l’enfant.',
            'mere_vivante.required' => 'Indiquez si la mère de l’enfant est vivante.',
            'pere_vivant.required' => 'Indiquez si le père de l’enfant est vivant.',
            'nom_structure_rib.required' => 'Précisez le nom de la structure titulaire du RIB.',
            'numero_acte_naissance.required_if' => 'Saisissez le numéro de l’acte de naissance de l’enfant.',
            'lieu_naissance.required' => 'Précisez le lieu de naissance (ville, pays) s’il n’est pas dans la liste.',
            'mere_nom.required' => 'La mère s’occupe de l’enfant : saisissez son nom (partie « Mère »).',
            'mere_prenoms.required' => 'La mère s’occupe de l’enfant : saisissez ses prénoms (partie « Mère »).',
            'pere_nom.required' => 'Le père s’occupe de l’enfant : saisissez son nom (partie « Père »).',
            'pere_prenoms.required' => 'Le père s’occupe de l’enfant : saisissez ses prénoms (partie « Père »).',
            'tuteur_sexe.required' => 'Indiquez le sexe du tuteur.',
            'tuteur_pret_continuer.required' => 'Indiquez si le parent ou tuteur est prêt à continuer à s’occuper de l’enfant.',
            'tuteur_raison_arret.required_if' => 'Indiquez pourquoi le parent ou tuteur ne peut pas continuer à s’occuper de l’enfant.',
            'maladie_nom.required_if' => 'Indiquez le nom de la maladie.',
            'suivi_clinique.required_if' => 'Indiquez si l’enfant bénéficie d’un suivi clinique.',
            'classe_precedente.required' => 'Indiquez la classe de l’année précédente (elle détermine si la moyenne est sur 10 ou sur 20).',
            'performance_scolaire.required' => 'Indiquez les performances scolaires de l’enfant.',
            'performance_difficultes.required' => 'Expliquez les difficultés scolaires de l’enfant.',
            'formation_duree_mois.required_if' => 'Indiquez la durée de la formation (en mois).',
            'formation_duree_recue_mois.required' => 'Indiquez la durée de formation déjà reçue (en mois).',
            'date_naissance.before_or_equal' => 'La date de naissance ne peut pas être dans le futur.',
            'date_naissance.after' => 'L’enfant doit avoir moins de 25 ans.',
            'contact_tuteur.regex' => 'Le numéro de téléphone doit comporter 8 chiffres (ex : 70 12 34 56).',
        ] + self::MESSAGES, Oev::DOCUMENTS + self::ATTRIBUTS);

        $validated['contact_tuteur'] = Oev::formaterTelephone($validated['contact_tuteur']);
        $validated['date_naissance_estimee'] = (bool) ($validated['date_naissance_estimee'] ?? false);
        // Liste des villages vide ou désactivée : non envoyée, l'ancien village ne doit pas rester
        $validated['village_id'] ??= null;
        $validated['statut'] = Oev::calculerStatut($validated['mere_vivante'], $validated['pere_vivant']);

        // Les informations masquées par une réponse ne sont pas conservées (ex. date de décès d'une mère vivante).
        $effacer = function (bool $condition, array $champs) use (&$validated): void {
            if ($condition) {
                foreach ($champs as $champ) {
                    $validated[$champ] = null;
                }
            }
        };
        $effacer($validated['mere_vivante'] !== 'non', ['mere_date_deces', 'mere_deces_confirme']);
        $effacer($validated['pere_vivant'] !== 'non', ['pere_date_deces', 'pere_deces_confirme']);
        $effacer(($validated['lieu_de_vie'] ?? null) !== 'autre', ['lieu_de_vie_precision']);
        $effacer(! $validated['handicap'], ['types_handicap', 'nature_handicap']);
        $effacer(! in_array($validated['groupe_population'] ?? null, Oev::GROUPES_MOBILES, true), ['lieu_provenance']);
        $effacer(! $validated['tuteur_a_cnib'], ['tuteur_cnib']);
        $effacer($validated['tuteur_lien'] !== 'autre_parent', ['tuteur_lien_precision']);
        $effacer((bool) $validated['tuteur_pret_continuer'], ['tuteur_raison_arret']);
        $effacer(! $validated['a_acte_naissance'], ['numero_acte_naissance']);
        $effacer(! ($validated['maladie_chronique'] ?? false), ['maladie_nom', 'suivi_clinique']);
        $effacer($validated['situation_scolaire'] !== 'scolarise', ['systeme_educatif', 'etablissement_actuel', 'type_etablissement', 'classe', 'frais_scolarite', 'performance_scolaire', 'performance_difficultes']);
        $effacer(($validated['performance_scolaire'] ?? null) !== 'difficultes', ['performance_difficultes']);
        $effacer(! in_array($validated['situation_scolaire'], ['scolarise', 'descolarise'], true), ['niveau_etude']);
        $effacer($validated['situation_scolaire'] === 'non_scolarise', ['etablissement_precedent', 'classe_precedente', 'moyenne_annuelle', 'appreciation']);
        $effacer(blank($validated['etablissement_precedent'] ?? null), ['classe_precedente', 'moyenne_annuelle', 'appreciation']);
        $effacer(! in_array($validated['situation_scolaire'], ['non_scolarise', 'descolarise'], true), ['raison_non_scolarisation', 'raison_non_scolarisation_precision']);
        $effacer(($validated['raison_non_scolarisation'] ?? null) !== 'autre', ['raison_non_scolarisation_precision']);
        $effacer(! ($validated['formation_professionnelle'] ?? false), ['formation_etat', 'formation_filiere', 'formation_type_centre', 'formation_duree_mois', 'formation_duree_recue_mois']);
        // Formation achevée : toute la durée a été reçue
        if (($validated['formation_etat'] ?? null) === 'achevee') {
            $validated['formation_duree_recue_mois'] = $validated['formation_duree_mois'];
        }

        // Lieu de naissance : commune du référentiel, ou lieu saisi quand il n'y figure pas
        if (($validated['lieu_naissance_commune_id'] ?? null) === 'autre') {
            $validated['lieu_naissance_commune_id'] = null;
        } else {
            $validated['lieu_naissance'] = null;
        }

        // Tuteur = parent(s) : nom et prénoms repris de la partie « Parents », sexe déduit
        $parents = Oev::parentsTuteurs($validated['tuteur_lien']);
        if ($parents !== []) {
            $validated['nom_tuteur'] = collect($parents)->map(fn ($p) => $validated["{$p}_nom"])->unique()->implode(' / ');
            $validated['prenom_tuteur'] = collect($parents)->map(fn ($p) => $validated["{$p}_prenoms"])->implode(' et ');
        }
        $validated['tuteur_sexe'] = match ($validated['tuteur_lien']) {
            'mere' => 'F',
            'pere' => 'M',
            'parents' => null,
            default => $validated['tuteur_sexe'] ?? null,
        };

        // Vulnérabilités : celles déduites des réponses sont ajoutées d'office ; « grossesse » ne concerne que les filles
        $vulnerabilites = array_diff((array) ($validated['vulnerabilites'] ?? []), ['orphelin', 'handicap', 'sans_acte_naissance']);
        if ($validated['sexe'] !== 'F') {
            $vulnerabilites = array_diff($vulnerabilites, ['grossesse']);
        }
        $vulnerabilites = array_values(array_unique(array_merge(Oev::vulnerabilitesAutomatiques($validated), $vulnerabilites)));
        $validated['vulnerabilites'] = $vulnerabilites ?: null;
        $effacer(! in_array('autre', $vulnerabilites, true), ['vulnerabilite_precision']);

        return collect($validated)->except(array_keys(Oev::DOCUMENTS))->all();
    }

    /**
     * Un DP ne saisit que des dossiers de sa province, un DR (complément) que de sa région.
     * Ajoute la règle correspondante à $regles et renvoie son message d'erreur.
     */
    private function limiterALaZone(Request $request, array &$regles): array
    {
        $perimetre = Perimetre::pour($request->user());

        if (! $perimetre->defini) {
            $champ = $perimetre->niveau === User::NIVEAU_REGION ? 'region_id' : 'province_id';
            $regles[$champ][] = Rule::in([]);

            return ["{$champ}.in" => 'Votre compte n’est rattaché à aucune localité : demandez à un administrateur de le compléter.'];
        }
        if ($perimetre->province) {
            $regles['province_id'][] = Rule::in([$perimetre->province->id]);

            return ['province_id.in' => "Vous ne pouvez enregistrer que des dossiers de votre province ({$perimetre->province->nom})."];
        }
        if ($perimetre->region) {
            $regles['region_id'][] = Rule::in([$perimetre->region->id]);

            return ['region_id.in' => "Vous ne pouvez enregistrer que des dossiers de votre région ({$perimetre->region->nom})."];
        }

        return [];
    }

    private function enregistrerDocuments(Request $request, Oev $oev): void
    {
        // Seules les pièces demandées pour cet enfant sont enregistrées (ex. pas d'acte s'il n'en a pas)
        foreach (array_keys($oev->piecesRequises()) as $type) {
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
