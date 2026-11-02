<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CloisonneParZone;
use App\Models\BeneficiaireSession;
use App\Services\Oev\ValidationOev;
use App\Services\Parrainage\ParcoursScolaire;
use App\Models\Oev;
use App\Models\OevDocument;
use App\Models\Region;
use App\Support\Perimetre;
use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OevController extends Controller implements HasMiddleware
{
    use CloisonneParZone;

    public function __construct(private readonly ValidationOev $validation)
    {
    }

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

    /** DP — « Constituer dossier enfant » : tous les dossiers, quel que soit leur état. */
    /**
     * « Constituer dossier enfant » : dossiers en cours (constitution, circuit, rejetés) d'un côté,
     * dossiers intégrés (devenus OEV) de l'autre (?vue=integres).
     */
    public function index(Request $request): View
    {
        $integres = $request->query('vue') === 'integres';
        $enCours = array_values(array_diff(array_keys(Oev::ETATS), [Oev::ETAT_INTEGRE]));
        $compter = fn (array $etats) => $this->cloisonner(Oev::visiblesPar($request->user())->etat(...$etats))->count();

        return $this->lister($request, 'oevs.index', $integres ? [Oev::ETAT_INTEGRE] : $enCours, null, $integres ? ['vue' => 'integres'] : [])
            ->with('vue', $integres ? 'integres' : 'en_cours')
            ->with('nbEnCours', $compter($enCours))
            ->with('nbIntegres', $compter([Oev::ETAT_INTEGRE]));
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

        // Région / province choisies, dans la zone de l'utilisateur
        $localite = $this->filtreLocalite($request);
        // OEV actifs : les désactivés sont dans l'onglet « OEV sortants » (une désactivation seulement demandée reste ici)
        $dansLaLocalite = fn ($q) => $localite['appliquer']($q)
            ->where(fn ($q) => $q->whereNull('desactivation_etat')->orWhere('desactivation_etat', '!=', Oev::DESACTIVE));

        $oevs = Oev::visiblesPar($request->user())->etat(Oev::ETAT_INTEGRE)
            ->with(['region', 'province', 'commune'])
            ->tap($dansLaLocalite)
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    foreach (['code', 'nom', 'prenom', 'nom_tuteur', 'prenom_tuteur', 'etablissement_actuel'] as $champ) {
                        $q->orWhereLike($champ, "%{$recherche}%");
                    }
                    $q->ouLocaliteContient("%{$recherche}%");
                });
            })
            ->orderByDesc('integre_at')
            ->paginate(15)
            ->withQueryString();

        $parStatut = Oev::visiblesPar($request->user())->etat(Oev::ETAT_INTEGRE)
            ->tap($dansLaLocalite)
            ->selectRaw('statut, count(*) as total')->groupBy('statut')
            ->pluck('total', 'statut');

        return view('oevs.liste', [
            'oevs' => $oevs,
            'compteurs' => collect(Oev::STATUTS)->mapWithKeys(fn ($l, $s) => [$s => (int) ($parStatut[$s] ?? 0)]),
            'filtres' => ['statut' => $statut, 'recherche' => $recherche, 'region_id' => $localite['region_id'], 'province_id' => $localite['province_id']],
            'localites' => $localite['localites'],
            // Onglet « OEV sortants » : désactivés, et demandes en attente (signalées en orange)
            'nbDesactives' => $this->cloisonner(Oev::etat(Oev::ETAT_INTEGRE)->where('desactivation_etat', Oev::DESACTIVE))->count(),
            'nbDemandes' => $this->cloisonner(Oev::etat(Oev::ETAT_INTEGRE)->where('desactivation_etat', Oev::DESACTIVATION_DEMANDEE))->count(),
        ]);
    }

    public function create(Request $request): View
    {
        // Le DP ne constitue que des dossiers de sa province : elle est proposée d'office
        $perimetre = Perimetre::pour($request->user());
        $oev = new Oev(['region_id' => $perimetre->region?->id, 'province_id' => $perimetre->province?->id]);

        return view('oevs.form', ['oev' => $oev, 'documents' => collect()] + $this->localitesDuFormulaire($request));
    }

    /**
     * Listes de localités du formulaire : tout le pays pour le lieu de naissance et la provenance,
     * mais seulement la zone de l'agent pour l'adresse (sa province pour le DP, sa région pour le DR),
     * comme l'exige la validation (voir ValidationOev::limiterALaZone).
     */
    private function localitesDuFormulaire(Request $request): array
    {
        // Sans les ~11 000 villages : ceux de la commune choisie sont chargés à la demande (localites.villages-commune)
        $localites = Region::arborescence();

        return ['localites' => $localites, 'localitesZone' => Perimetre::pour($request->user())->filtrerArborescence($localites)];
    }

    public function store(Request $request)
    {
        $validated = $this->validation->valider($request);

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
        abort_if($oev->statut_dossier === Oev::ETAT_BROUILLON && ! $request->user()->can(Permissions::CONSTITUER_DOSSIERS), 403);

        $oev->load(['documents.auteur', 'createur', 'soumetteur', 'verificateur', 'integrateur', 'demandeurComplement', 'auteurRejet', 'region', 'province', 'commune', 'village', 'lieuNaissanceCommune', 'lieuProvenanceCommune']);

        return view('oevs.show', [
            'oev' => $oev,
            'documents' => $oev->documents->keyBy('type'),
            // Parrainages de l'État (listes définitives) et leur suivi de fin d'année
            'parrainages' => BeneficiaireSession::parraines()->where('oev_id', $oev->id)->with(['session', 'suivi'])->latest('id')->get(),
            // Parcours scolaire et professionnel année par année (reconstitué à partir des suivis)
            'cursus' => app(ParcoursScolaire::class)->pour($oev),
        ]);
    }

    public function edit(Request $request, Oev $oev)
    {
        if ($refus = $this->refuserSiNonModifiable($request, $oev)) {
            return $refus;
        }

        return view('oevs.form', ['oev' => $oev, 'documents' => $oev->documents()->get()->keyBy('type')] + $this->localitesDuFormulaire($request));
    }

    public function update(Request $request, Oev $oev)
    {
        if ($refus = $this->refuserSiNonModifiable($request, $oev)) {
            return $refus;
        }
        $validated = $this->validation->valider($request);

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
            abort_unless($request->user()->canAny([Permissions::VALIDER_DOSSIERS, Permissions::INTEGRER_OEV]), 403);

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
    /** @param  array  $parametresVue  paramètres conservés par les onglets et le formulaire de la liste (ex : ['vue' => 'integres']) */
    private function lister(Request $request, string $vue, array $etats, ?string $etatParDefaut, array $parametresVue = []): View
    {
        $etat = $request->query('etat', $etatParDefaut);
        $etat = in_array($etat, $etats, true) ? $etat : null;
        $statut = $request->query('statut');
        $recherche = trim((string) $request->query('q'));
        $localite = $this->filtreLocalite($request);

        $oevs = Oev::with('documents:id,oev_id,type')
            ->visiblesPar($request->user())
            ->tap($localite['appliquer'])
            ->etat(...($etat ? [$etat] : $etats))
            ->when(array_key_exists((string) $statut, Oev::STATUTS), fn ($q) => $q->where('statut', $statut))
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    foreach (['code', 'numero_dossier', 'nom', 'prenom', 'nom_tuteur', 'prenom_tuteur', 'etablissement_actuel'] as $champ) {
                        $q->orWhereLike($champ, "%{$recherche}%");
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

        $compteurs = Oev::query()->visiblesPar($request->user())->tap($localite['appliquer'])->etat(...$etats)
            ->selectRaw('statut_dossier, count(*) as total')->groupBy('statut_dossier')
            ->pluck('total', 'statut_dossier');

        return view($vue, [
            'oevs' => $oevs,
            'etats' => $etats,
            'compteurs' => collect($etats)->mapWithKeys(fn ($e) => [$e => (int) ($compteurs[$e] ?? 0)]),
            'filtres' => compact('etat', 'statut', 'recherche') + ['region_id' => $localite['region_id'], 'province_id' => $localite['province_id']],
            'localites' => $localite['localites'],
            'parametresVue' => $parametresVue,
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
            $utilisateur->can(Permissions::CONSTITUER_DOSSIERS)
            || ($utilisateur->can(Permissions::VALIDER_DOSSIERS) && $oev->statut_dossier !== Oev::ETAT_BROUILLON),
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
