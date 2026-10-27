<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\PaiementEtablissement;
use App\Models\Province;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Services\Parrainage\ExportPaiementSession;
use App\Services\Parrainage\FichiersExtraction;
use App\Services\Parrainage\PaiementsParrainage;
use App\Support\Montant;
use App\Support\Perimetre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Onglet « Paiement » d'une session dont la liste est validée : établissements à payer (banque, RIB, nombre
 * d'OEV, montant) et récapitulatif par région, consultables sur la plateforme sans extraction. Le niveau
 * central enregistre la réception des fonds par un établissement ou par toute une région ; le DR consulte
 * sa région.
 */
class PaiementSessionController extends Controller
{
    private const PAR_PAGE = 50;

    public const AFFICHAGES = ['etablissement' => 'Établissement', 'region' => 'Région'];

    public const FILTRES_PAIEMENT = ['a_payer' => 'À payer', 'recu' => 'Fonds reçus'];

    public function __construct(private readonly PaiementsParrainage $paiements)
    {
    }

    public function index(Request $request, SessionParrainage $session): View|RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless(self::peutConsulter($utilisateur), 403);
        if (! in_array($session->etat, PaiementsParrainage::ETATS_PAIEMENT, true)) {
            return redirect()->route('parrainage.sessions.show', $session)
                ->with('error', 'Le paiement s’ouvre une fois la liste de la session validée.');
        }

        $zone = Perimetre::pour($utilisateur, $request);
        $affichage = array_key_exists((string) $request->query('par'), self::AFFICHAGES) ? (string) $request->query('par') : 'etablissement';
        $filtres = [
            'region_id' => $zone->region?->id,
            'province_id' => $zone->province?->id,
            'q' => trim((string) $request->query('q')) ?: null,
            'paiement' => array_key_exists((string) $request->query('paiement'), self::FILTRES_PAIEMENT) ? (string) $request->query('paiement') : null,
        ];
        $regions = Region::orderBy('nom')->pluck('nom', 'id');

        return view('parrainage.sessions.paiements', [
            'session' => $session,
            'affichage' => $affichage,
            'filtres' => $filtres,
            'zone' => $zone,
            'central' => $utilisateur->niveau() === User::NIVEAU_CENTRAL,
            'regions' => $regions,
            'localites' => $zone->filtrerArborescence(Region::arborescence()),
            'totaux' => $this->paiements->totaux($session, Perimetre::pour($utilisateur)),
            'etablissements' => $affichage === 'etablissement'
                ? $this->paiements->parEtablissement($session, Perimetre::pour($utilisateur), $filtres)
                    ->orderByRaw('e.nom is null')->orderBy('region_id')->orderBy('e.nom')
                    ->paginate(self::PAR_PAGE)->withQueryString()
                : null,
            'provinces' => Province::pluck('nom', 'id'),
            'parRegion' => $affichage === 'region'
                ? $this->paiements->parRegion($session, Perimetre::pour($utilisateur), $filtres)->sortBy(fn ($l) => $regions[$l->region_id] ?? 'zzz')->values()
                : null,
            'peutEnregistrer' => $this->peutEnregistrer($utilisateur, $session),
        ]);
    }

    /**
     * Fichier Excel du paiement : feuilles et colonnes choisies dans la fenêtre d'export, limité à une région
     * ou aux établissements à payer (ou payés). Taille contrôlée avant de préparer le fichier ; export tracé.
     */
    public function export(Request $request, SessionParrainage $session, ExportPaiementSession $export, FichiersExtraction $fichiers): StreamedResponse|RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless(self::peutConsulter($utilisateur), 403);
        abort_unless(in_array($session->etat, PaiementsParrainage::ETATS_PAIEMENT, true), 404);
        FichiersExtraction::prevoirGrosFichier();

        $donnees = $request->validate([
            'feuilles' => ['required', 'array', 'min:1'],
            'feuilles.*' => [Rule::in(array_keys(ExportPaiementSession::FEUILLES))],
            'colonnes' => ['nullable', 'array'],
            'region_id' => ['nullable', 'integer', Rule::exists('regions', 'id')],
            'paiement' => ['nullable', Rule::in(array_keys(self::FILTRES_PAIEMENT))],
        ], ['feuilles.required' => 'Cochez au moins une feuille à extraire.']);

        $zone = Perimetre::pour($utilisateur);
        // Le DR reste dans sa région ; le niveau central peut se limiter à une région
        $filtres = [
            'region_id' => $zone->region?->id ?? ($donnees['region_id'] ?? null),
            'province_id' => $zone->province?->id,
            'paiement' => $donnees['paiement'] ?? null,
        ];
        $colonnes = collect($donnees['feuilles'])->unique()
            ->mapWithKeys(fn ($feuille) => [$feuille => ExportPaiementSession::colonnes($feuille, (array) ($donnees['colonnes'][$feuille] ?? []) ?: null)])
            ->all();

        if (isset($colonnes['nominatif'])) {
            $lignes = $export->nombreNominatif($session, $zone, $filtres);
            $max = FichiersExtraction::lignesExcelMax(count($colonnes['nominatif']));
            if ($lignes > $max) {
                return back()->with('error', Montant::nombre($lignes) . ' OEV dans la liste nominative : avec ' . count($colonnes['nominatif'])
                    . ' colonne(s), le fichier est limité à ' . Montant::nombre($max) . ' lignes. Choisissez une région ou cochez moins de colonnes.');
            }
        }

        $region = $filtres['region_id'] ? Region::whereKey($filtres['region_id'])->value('nom') : null;
        JournalActivite::consigner('extraction.paiement', $session, "Fichier de paiement de la session {$session->reference()} (Excel)", [
            'feuilles' => array_keys($colonnes),
            'colonnes' => array_map('array_keys', $colonnes),
            'filtres' => array_filter(['region' => $region, 'paiement' => $filtres['paiement']]),
        ], $utilisateur);

        $entete = [
            'titre' => "Paiement — Session {$session->reference()}",
            'lignes' => array_values(array_filter([
                "{$session->description} · {$session->libelleTypeAppui()} · {$session->libelleEtat()}",
                $region ? "Région : {$region}" : null,
                $filtres['paiement'] ? 'Établissements : ' . mb_strtolower(self::FILTRES_PAIEMENT[$filtres['paiement']]) : null,
                'Extrait le ' . now()->format('d/m/Y à H:i') . " par {$utilisateur->name}",
            ])),
        ];

        return $fichiers->classeurExcel($export->feuilles($session, $zone, $filtres, $colonnes), $entete,
            'paiement-session-' . $session->annee . '-' . $session->numero . '-' . now()->format('Ymd-His') . '.xlsx');
    }

    /** Réception des fonds : établissements cochés, ou tous ceux d'une région pas encore payés. */
    public function store(Request $request, SessionParrainage $session): RedirectResponse
    {
        abort_unless($this->peutEnregistrer($request->user(), $session), 403);
        $donnees = $request->validate([
            'etablissements' => ['required_without:region_id', 'array'],
            'etablissements.*' => ['integer'],
            'region_id' => ['nullable', 'integer', Rule::exists('regions', 'id')],
            'recu_le' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [
            'etablissements.required_without' => 'Cochez au moins un établissement.',
            'recu_le.required' => 'Indiquez la date de réception des fonds.',
            'recu_le.before_or_equal' => 'La date de réception ne peut pas être dans le futur.',
        ]);

        $zone = Perimetre::pour($request->user());
        $libelle = null;
        if (! empty($donnees['region_id'])) {
            $ids = $this->paiements->parEtablissement($session, $zone, ['region_id' => $donnees['region_id'], 'paiement' => 'a_payer'])
                ->get()->pluck('etablissement_id')->filter()->all();
            $libelle = 'Fonds reçus dans la région ' . Region::whereKey($donnees['region_id'])->value('nom') . ' (' . count($ids) . ' établissement(s))';
        } else {
            $ids = array_map('intval', $donnees['etablissements']);
        }

        $nombre = $this->paiements->enregistrerReception($session, $zone, $ids, $donnees['recu_le'], $donnees['reference'] ?? null, $request->user(), $libelle);

        return back()->with($nombre ? 'success' : 'error', $nombre
            ? "Réception des fonds enregistrée pour {$nombre} établissement(s)."
            : 'Aucun établissement à enregistrer : ils ont déjà reçu les fonds, ou aucun n’est sélectionné.');
    }

    public function destroy(Request $request, SessionParrainage $session, PaiementEtablissement $paiement): RedirectResponse
    {
        abort_unless((int) $paiement->session_parrainage_id === (int) $session->id, 404);
        abort_unless($this->peutEnregistrer($request->user(), $session), 403);

        $this->paiements->annuler($paiement, $request->user());

        return back()->with('success', 'Réception des fonds annulée.');
    }

    /** Niveau central et DR (leur région). */
    public static function peutConsulter(User $utilisateur): bool
    {
        return in_array($utilisateur->niveau(), [User::NIVEAU_CENTRAL, User::NIVEAU_REGION], true);
    }

    /** Niveau central gestionnaire des sessions, tant que la session n'est pas clôturée. */
    private function peutEnregistrer(User $utilisateur, SessionParrainage $session): bool
    {
        return $utilisateur->niveau() === User::NIVEAU_CENTRAL
            && $utilisateur->can('gérer sessions parrainage')
            && in_array($session->etat, [SessionParrainage::ETAT_VALIDEE, SessionParrainage::ETAT_PAIEMENT], true);
    }
}
