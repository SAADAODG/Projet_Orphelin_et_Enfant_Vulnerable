<?php

namespace App\Http\Controllers;

use App\Models\BeneficiaireSession;
use App\Models\Oev;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Services\Parrainage\ParrainageService;
use App\Services\Parrainage\SelectionParrainage;
use App\Services\Parrainage\TransitionSessionInvalide;
use App\Support\Montant;
use App\Support\Perimetre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Module « Sélection » d'une session de parrainage : le niveau central filtre les OEV éligibles
 * (vulnérabilité, âge, urgence du besoin…), les ajoute à la liste de la session puis valide la liste
 * définitive. Les DR et DP consultent la sélection de leur zone.
 */
class SelectionParrainageController extends Controller
{
    private const PAR_PAGE = 50;

    /** Au-delà, « tout ajouter » demande d'affiner les filtres. */
    private const AJOUT_MAX = 2000;

    public function __construct(
        private readonly SelectionParrainage $selection,
        private readonly ParrainageService $parrainage,
    ) {
    }

    public function index(Request $request, SessionParrainage $session): View
    {
        $session->load('quotas');
        $utilisateur = $request->user();
        $perimetre = Perimetre::pour($utilisateur);
        $gere = $utilisateur->can('gérer sessions parrainage');
        $modifiable = $gere && $session->parametresModifiables();
        // Tant que la liste est modifiable, on arrive sur tous les éligibles : les filtres ne font que réduire la liste
        $onglet = match ($request->query('onglet')) {
            'liste' => 'liste',
            'eligibles' => $session->parametresModifiables() ? 'eligibles' : 'liste',
            default => $modifiable ? 'eligibles' : 'liste',
        };
        $filtres = $this->lireFiltres($request);

        $lignes = $session->beneficiaires()
            ->whereHas('oev', fn ($q) => $perimetre->appliquer($q))
            ->with(['oev.region', 'oev.province', 'oev.commune', 'region'])
            ->get();

        // Tous les OEV intégrés de la zone sont listés ; les non éligibles le sont avec leur motif, sans pouvoir être ajoutés
        $eligibles = $onglet === 'eligibles'
            ? $this->selection->candidats($session, $filtres)
                ->where(fn ($q) => $perimetre->appliquer($q))
                ->with(['region', 'province', 'commune'])
                ->paginate(self::PAR_PAGE)
                ->withQueryString()
            : null;
        $motifs = $eligibles ? $this->selection->motifsInegibilite($session, $eligibles->items()) : [];
        // Résultat du dernier parrainage, pour décider d'un nouveau
        $suivis = $eligibles ? $this->selection->derniersSuivis($eligibles->items()) : collect();
        // Coût estimé des éligibles correspondant aux filtres (ceux que « Tout ajouter » ajouterait)
        $estimation = $eligibles && ($filtres['eligibilite'] ?? null) !== 'non'
            ? $this->selection->estimationCout($session, $this->selection->eligibles($session, $filtres)->where(fn ($q) => $perimetre->appliquer($q)))
            : null;
        $nbAjoutables = $estimation['nombre'] ?? 0;

        $budget = $this->selection->budget($session);

        return view('parrainage.selection.index', [
            'session' => $session,
            'onglet' => $onglet,
            'gere' => $gere,
            'modifiable' => $modifiable,
            'retenus' => $lignes->where('statut', BeneficiaireSession::RETENU)->sortByDesc('score')->values(),
            'attente' => $lignes->where('statut', BeneficiaireSession::ATTENTE)->sortBy('rang')->values(),
            'eligibles' => $eligibles,
            'motifs' => $motifs,
            'suivis' => $suivis,
            'nbAjoutables' => $nbAjoutables,
            'estimation' => $estimation,
            'filtres' => $filtres,
            'nbFiltres' => count(array_filter($filtres, fn ($v) => $v !== null && $v !== '' && $v !== [])),
            'budget' => $budget,
            'regions' => Region::orderBy('nom')->get(['id', 'nom']),
            'localites' => $perimetre->filtrerArborescence(Region::arborescence()),
        ]);
    }

    /** Ajout des OEV cochés, ou de tous les éligibles correspondant aux filtres (« tout ajouter »). */
    public function ajouter(Request $request, SessionParrainage $session): RedirectResponse
    {
        if ($refus = $this->refuserSiFigee($session)) {
            return $refus;
        }

        if ($request->boolean('tous')) {
            $requete = $this->selection->eligibles($session, $this->lireFiltres($request));
            if ((clone $requete)->count() > self::AJOUT_MAX) {
                return back()->with('error', 'Trop d’OEV correspondent à ces filtres (plus de ' . self::AJOUT_MAX . ') : affinez la recherche avant de tout ajouter.');
            }
            // Le tri par score (colonne calculée) est retiré : seuls les identifiants sont lus
            $ids = $requete->reorder()->pluck('oevs.id')->all();
        } else {
            $ids = array_map('intval', (array) $request->input('oev_ids', []));
        }

        if (! $ids) {
            return back()->with('error', 'Cochez au moins un OEV à ajouter.');
        }

        $compte = $this->selection->ajouter($session, $ids, $request->user());

        return redirect()->route('parrainage.sessions.selection', $session)->with('success', match (true) {
            $compte['retenus'] + $compte['attente'] === 0 => 'Aucun OEV ajouté : ils ne sont plus éligibles à cette session.',
            $compte['attente'] === 0 => "{$compte['retenus']} OEV retenu(s) sur la liste de la session.",
            default => "{$compte['retenus']} OEV retenu(s) ; {$compte['attente']} placé(s) en liste d’attente (enveloppe ou quota atteint).",
        });
    }

    /** Liste d'attente → liste de la session, ou correction des frais réels. */
    public function modifier(Request $request, SessionParrainage $session, BeneficiaireSession $ligne): RedirectResponse
    {
        abort_unless((int) $ligne->session_parrainage_id === (int) $session->id, 404);
        if ($refus = $this->refuserSiFigee($session)) {
            return $refus;
        }

        if ($request->input('action') === 'retenir') {
            $erreur = $this->selection->retenir($session, $ligne, $request->user());

            return back()->with($erreur ? 'error' : 'success', $erreur ? 'Impossible : ' . $erreur : 'OEV retenu sur la liste de la session.');
        }

        $request->merge(['frais_reels' => Montant::normaliserSaisie($request->input('frais_reels'))]);
        $donnees = $request->validate(['frais_reels' => ['nullable', 'integer', 'min:0']], [
            'frais_reels.integer' => 'Les frais réels doivent être un montant entier en FCFA.',
        ]);
        $frais = isset($donnees['frais_reels']) ? (int) $donnees['frais_reels'] : null;
        $erreur = $this->selection->modifierFrais($session, $ligne, $frais, $request->user());

        return back()->with($erreur ? 'error' : 'success', $erreur ? 'Impossible : ' . $erreur : 'Frais réels mis à jour.');
    }

    /** Montants estimatifs : enfant d'âge scolaire à inscrire, OEV en formation professionnelle. */
    public function estimations(Request $request, SessionParrainage $session): RedirectResponse
    {
        if ($refus = $this->refuserSiFigee($session)) {
            return $refus;
        }

        $request->merge([
            'estimation_scolarisation' => Montant::normaliserSaisie($request->input('estimation_scolarisation')),
            'estimation_formation' => Montant::normaliserSaisie($request->input('estimation_formation')),
        ]);
        $donnees = $request->validate([
            'estimation_scolarisation' => ['nullable', 'integer', 'min:0'],
            'estimation_formation' => ['nullable', 'integer', 'min:0'],
        ], [
            'integer' => 'Le montant estimatif doit être un montant entier en FCFA.',
            'min' => 'Le montant estimatif ne peut pas être négatif.',
        ]);
        $montant = fn (string $cle) => isset($donnees[$cle]) ? (int) $donnees[$cle] : null;

        $recalculees = $this->selection->modifierEstimations($session, $montant('estimation_scolarisation'), $montant('estimation_formation'), $request->user());

        return back()->with('success', 'Montants estimatifs enregistrés' . ($recalculees ? " ; {$recalculees} OEV déjà ajouté(s) recalculé(s)." : '.'));
    }

    public function retirer(Request $request, SessionParrainage $session, BeneficiaireSession $ligne): RedirectResponse
    {
        abort_unless((int) $ligne->session_parrainage_id === (int) $session->id, 404);
        if ($refus = $this->refuserSiFigee($session)) {
            return $refus;
        }

        $this->selection->retirer($session, $ligne, $request->user());

        return back()->with('success', 'OEV retiré de la sélection.');
    }

    /** Validation de la liste définitive : la session passe à « Validée » (liste et budget figés). */
    public function valider(Request $request, SessionParrainage $session): RedirectResponse
    {
        try {
            $this->parrainage->changerEtatSession($session->id, SessionParrainage::ETAT_VALIDEE, $request->user());
        } catch (TransitionSessionInvalide $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('parrainage.sessions.selection', $session)
            ->with('success', 'Liste définitive validée : la session est maintenant « Validée ».');
    }

    private function refuserSiFigee(SessionParrainage $session): ?RedirectResponse
    {
        return $session->parametresModifiables() ? null : redirect()->route('parrainage.sessions.selection', $session)
            ->with('error', "La session est « {$session->libelleEtat()} » : sa liste est figée.");
    }

    /**
     * Filtres de recherche des éligibles, limités aux valeurs connues. Sauf la recherche, l'âge et
     * l'éligibilité, chaque filtre accepte plusieurs valeurs (ex : plusieurs régions ou vulnérabilités).
     */
    private function lireFiltres(Request $request): array
    {
        $choix = fn (string $cle, array $liste) => array_key_exists((string) $request->input($cle), $liste) ? (string) $request->input($cle) : null;
        $plusieurs = fn (string $cle, array $liste) => array_values(array_unique(array_filter(
            array_map('strval', (array) $request->input($cle, [])),
            fn ($v) => array_key_exists($v, $liste),
        )));
        $identifiants = fn (string $cle) => array_values(array_unique(array_filter(array_map('intval', (array) $request->input($cle, [])))));
        $entier = fn (string $cle, int $max) => is_numeric($request->input($cle)) ? min($max, max(0, (int) $request->input($cle))) : null;

        return [
            'q' => trim((string) $request->input('q')) ?: null,
            'type' => $plusieurs('type', [SessionParrainage::TYPE_SCOLAIRE => 1, SessionParrainage::TYPE_FORMATION => 1]),
            // Régions entières, ou seulement certaines provinces de ces régions
            'regions' => $identifiants('regions'),
            'provinces' => $identifiants('provinces'),
            'sexe' => $plusieurs('sexe', Oev::SEXES),
            'age_min' => $entier('age_min', 25),
            'age_max' => $entier('age_max', 25),
            'statut' => $plusieurs('statut', Oev::STATUTS),
            'vulnerabilite' => $plusieurs('vulnerabilite', Oev::VULNERABILITES),
            'handicap' => $plusieurs('handicap', ['1' => 1, '0' => 1]),
            'niveau_priorite' => $plusieurs('niveau_priorite', Oev::NIVEAUX),
            'scolarite' => $plusieurs('scolarite', SelectionParrainage::SITUATIONS),
            'niveau_etude' => $plusieurs('niveau_etude', Oev::NIVEAUX_ETUDE),
            'groupe_population' => $plusieurs('groupe_population', Oev::GROUPES_POPULATION),
            'eligibilite' => $choix('eligibilite', ['oui' => 1, 'non' => 1]),
        ];
    }
}
