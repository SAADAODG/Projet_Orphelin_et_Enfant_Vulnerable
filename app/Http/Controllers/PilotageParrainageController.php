<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\JournalActivite;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\Province;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Services\Parrainage\ExtractionsParrainage;
use App\Services\Parrainage\FichiersExtraction;
use App\Services\Parrainage\PilotageParrainage;
use App\Services\Parrainage\SourceSelection;
use App\Support\Perimetre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module « Pilotage » du parrainage : tableau de bord et extractions, chacun dans sa zone
 * (DP : sa province, DR : sa région, niveau central : tout le pays). Chaque extraction est tracée dans le journal.
 */
class PilotageParrainageController extends Controller
{
    /** États d'une session dont la liste définitive peut être extraite pour paiement. */
    /** Filtres à choix multiple de la liste pour un parrain. */
    private const FILTRES_MULTIPLES = ['regions', 'provinces', 'sexe', 'statut', 'vulnerabilite', 'niveau_priorite', 'niveau_etude', 'handicap'];

    private const ETATS_PAIEMENT = [SessionParrainage::ETAT_VALIDEE, SessionParrainage::ETAT_PAIEMENT, SessionParrainage::ETAT_CLOTUREE];

    public function __construct(
        private readonly PilotageParrainage $pilotage,
        private readonly ExtractionsParrainage $extractions,
        private readonly FichiersExtraction $fichiers,
        private readonly SourceSelection $source,
    ) {
    }

    public function tableauDeBord(Request $request): View
    {
        $f = $this->pilotage->filtres($request, $request->user());

        return view('parrainage.pilotage.tableau', [
            'f' => $f,
            'indicateurs' => $this->pilotage->indicateurs($f),
            'disponible' => $this->source->disponible(),
            'regions' => $f['perimetre']->niveau === User::NIVEAU_CENTRAL ? Region::with(['provinces' => fn ($q) => $q->orderBy('nom')])->orderBy('nom')->get() : collect(),
        ]);
    }

    public function extractions(Request $request): View
    {
        $utilisateur = $request->user();
        $vue = $request->query('vue') === 'paiement' && $this->peutExtrairePaiement($utilisateur) ? 'paiement' : 'parrain';
        $parrain = Parrain::with(['zones.region', 'zones.province', 'engagements'])->find($request->integer('parrain_id'));
        $filtresParrain = $parrain ? $this->filtresListeParrain($request, $parrain) : null;
        $liste = $parrain ? $this->extractions->listeParrain($parrain, $utilisateur, $filtresParrain) : collect();

        return view('parrainage.pilotage.extractions', [
            'vue' => $vue,
            'peutPaiement' => $this->peutExtrairePaiement($utilisateur),
            'central' => $utilisateur->niveau() === User::NIVEAU_CENTRAL,
            'disponible' => $this->source->disponible(),
            'localites' => Perimetre::pour($utilisateur)->filtrerArborescence(Region::arborescence()),
            'sessionsPaiement' => SessionParrainage::whereIn('etat', self::ETATS_PAIEMENT)->orderByDesc('annee')->orderBy('numero')->get(),
            'sessions' => SessionParrainage::orderByDesc('annee')->orderBy('numero')->get(),
            'etablissements' => $this->source->etablissements(),
            'parrains' => Parrain::actifs()->orderBy('nom')->get(['id', 'nom']),
            'parrain' => $parrain,
            'f' => $filtresParrain,
            'natures' => NatureAppui::actives()->get(),
            'liste' => $liste,
            'apercu' => $liste->take(10),
            // Aperçu à l'écran (agents) : nom et prénom ; l'extraction, elle, est anonymisée par défaut
            'colonnesApercu' => ExtractionsParrainage::colonnesListeParrain(ExtractionsParrainage::DONNEES_APERCU),
            'donneesParrain' => ExtractionsParrainage::donneesListeParrain(),
            'donneesParrainChoisies' => (array) $request->input('donnees', ExtractionsParrainage::DONNEES_PARRAIN_PAR_DEFAUT),
            'colonnesEtablissement' => ExtractionsParrainage::COLONNES_ETABLISSEMENT,
            'colonnesNominatif' => ExtractionsParrainage::COLONNES_NOMINATIF,
        ]);
    }

    /** Liste pour paiement (Excel ou PDF) : niveau central et DR (leur région), session au moins « Validée ». */
    public function paiement(Request $request): Response|RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless($this->peutExtrairePaiement($utilisateur), 403);

        $donnees = $request->validate([
            'session_id' => ['required', 'integer', Rule::exists('sessions_parrainage', 'id')->whereIn('etat', self::ETATS_PAIEMENT)],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'region_id' => ['nullable', 'integer'],
            'province_id' => ['nullable', 'integer'],
            'commune_id' => ['nullable', 'integer'],
            'etablissement_id' => ['nullable', 'integer'],
            'type_appui' => ['nullable', Rule::in(array_keys(SessionParrainage::TYPES_APPUI))],
            'colonnes_etablissement' => ['nullable', 'array'],
            'colonnes_nominatif' => ['nullable', 'array'],
        ], ['session_id.exists' => 'Choisissez une session validée : la liste définitive doit être arrêtée.']);
        // Données à faire figurer : celles cochées (nombre d'OEV et montants toujours présents : ils portent les totaux)
        $colonnesEtablissement = ExtractionsParrainage::colonnesChoisies(ExtractionsParrainage::COLONNES_ETABLISSEMENT,
            $donnees['colonnes_etablissement'] ?? null, ExtractionsParrainage::COLONNES_ETABLISSEMENT_OBLIGATOIRES);
        $colonnesNominatif = ExtractionsParrainage::colonnesChoisies(ExtractionsParrainage::COLONNES_NOMINATIF,
            $donnees['colonnes_nominatif'] ?? null, ExtractionsParrainage::COLONNES_NOMINATIF_OBLIGATOIRES, ExtractionsParrainage::NOMINATIF_PAR_DEFAUT);

        $session = SessionParrainage::findOrFail($donnees['session_id']);
        $filtres = collect($donnees)->except(['session_id', 'format', 'colonnes_etablissement', 'colonnes_nominatif'])->filter()->all();
        $resultat = $this->extractions->paiement($session, $utilisateur, $filtres);

        JournalActivite::consigner('extraction.paiement', $session, "Liste pour paiement de la session {$session->reference()} ({$donnees['format']})", [
            'filtres' => $filtres,
            'lignes' => $resultat['total_oev'],
            'etablissements' => $resultat['etablissements']->count(),
            'colonnes' => ['par_etablissement' => array_keys($colonnesEtablissement), 'nominatif' => array_keys($colonnesNominatif)],
            'format' => $donnees['format'],
        ]);

        $entete = [
            'titre' => "Liste pour paiement — Session {$session->reference()}",
            'lignes' => array_filter([
                "Session : {$session->description} · {$session->libelleTypeAppui()} · {$session->libelleEtat()}",
                'Extraite le ' . now()->format('d/m/Y à H:i') . " par {$utilisateur->name}",
                $this->libelleFiltres($filtres),
                $resultat['disponible'] ? null : 'Données non disponibles : le module Sélection n’est pas encore branché.',
            ]),
        ];
        $nom = 'paiement-session-' . $session->annee . '-' . $session->numero . '-' . now()->format('Ymd-His') . '.' . $donnees['format'];

        return $donnees['format'] === 'pdf'
            ? $this->fichiers->paiementPdf($resultat, $entete, $nom, $colonnesEtablissement, $colonnesNominatif)
            : $this->fichiers->paiementExcel($resultat, $entete, $nom, $colonnesEtablissement, $colonnesNominatif);
    }

    /**
     * Liste d'OEV à proposer à un parrain (Excel ou PDF), avec les données cochées ; anonymisée par défaut.
     * Données d'identité (nom, code, date de naissance, tuteur…) : niveau central seulement, après
     * confirmation explicite, tracée dans le journal.
     */
    public function listeParrain(Request $request): Response|RedirectResponse
    {
        $utilisateur = $request->user();
        $request->validate([
            'parrain_id' => ['required', 'integer', Rule::exists('parrains', 'id')],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
        ]);

        $donnees = array_values(array_intersect(array_keys(ExtractionsParrainage::donneesListeParrain()),
            array_map('strval', (array) $request->input('donnees', ExtractionsParrainage::DONNEES_PARRAIN_PAR_DEFAUT))));
        if (! $donnees) {
            return back()->withInput()->withErrors(['donnees' => 'Cochez au moins une donnée à extraire.']);
        }
        $identiteComplete = ExtractionsParrainage::comporteIdentite($donnees);
        if ($identiteComplete && $utilisateur->niveau() !== User::NIVEAU_CENTRAL) {
            return back()->withInput()->withErrors(['donnees' => 'Les données d’identité des enfants (nom, code, date de naissance, tuteur) sont réservées au niveau central.']);
        }
        if ($identiteComplete && ! $request->boolean('confirmation_identite')) {
            return back()->withInput()->withErrors(['confirmation_identite' => 'Confirmez l’extraction des données d’identité des enfants.']);
        }

        $parrain = Parrain::with(['zones', 'engagements'])->findOrFail($request->integer('parrain_id'));
        $f = $this->filtresListeParrain($request, $parrain);
        if ($f['source'] === 'attente' && ! $f['session']) {
            return back()->withInput()->withErrors(['session_id' => 'Choisissez la session dont la liste d’attente est extraite.']);
        }
        $oevs = $this->extractions->listeParrain($parrain, $utilisateur, $f);
        $format = $request->input('format');

        $resume = collect([
            'source' => $f['source'],
            'session' => $f['session']?->reference(),
            'annee' => $f['annee'],
            'nature' => $f['nature']?->code,
            'zone_parrain' => $f['zone_parrain'],
        ] + collect($f)->only(self::FILTRES_MULTIPLES)->all() + collect($f)->only(['age_min', 'age_max'])->all())
            ->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->all();

        JournalActivite::consigner(
            $identiteComplete ? 'extraction.liste_parrain_identite' : 'extraction.liste_parrain',
            $parrain,
            "Liste d’OEV pour {$parrain->nom} (" . ($identiteComplete ? 'identité complète' : 'anonymisée') . ", {$format})",
            ['filtres' => $resume, 'donnees' => $donnees, 'lignes' => $oevs->count(), 'identite_complete' => $identiteComplete, 'format' => $format],
        );

        $entete = [
            'titre' => "OEV proposés à {$parrain->nom}",
            'lignes' => array_filter([
                $f['source'] === 'attente'
                    ? "Source : liste d’attente de la session {$f['session']->reference()}"
                    : "Source : OEV sans appui « " . ($f['nature']?->libelle ?? 'toutes natures') . " » pour {$f['annee']}",
                'Extraite le ' . now()->format('d/m/Y à H:i') . " par {$utilisateur->name}",
                $identiteComplete ? 'Document confidentiel : identité complète des enfants.' : 'Liste anonymisée.',
                $f['source'] === 'attente' && ! $this->source->disponible() ? 'Données non disponibles : le module Sélection n’est pas encore branché.' : null,
            ]),
        ];
        $colonnes = ExtractionsParrainage::colonnesListeParrain($donnees);
        $nom = 'oev-' . Str::slug($parrain->nom) . '-' . now()->format('Ymd-His') . '.' . $format;

        return $format === 'pdf'
            ? $this->fichiers->listePdf($oevs, $colonnes, $entete, $nom)
            : $this->fichiers->listeExcel($oevs, $colonnes, $entete, $nom);
    }

    /**
     * Filtres de la liste pour un parrain. Au premier affichage (pas de « filtre » dans la requête),
     * la liste est préfiltrée sur la zone du parrain et la première nature de ses engagements.
     */
    private function filtresListeParrain(Request $request, Parrain $parrain): array
    {
        $preferences = $this->extractions->preferencesParrain($parrain);
        $premierAffichage = ! $request->has('filtre');
        $natureId = $premierAffichage ? ($preferences['natures'][0] ?? null) : ($request->integer('nature_id') ?: null);
        $annee = (string) $request->input('annee', '');
        $plusieurs = fn (string $cle, array $liste) => array_values(array_unique(array_filter(
            array_map('strval', (array) $request->input($cle, [])),
            fn ($v) => array_key_exists($v, $liste),
        )));

        return [
            'source' => $request->input('source') === 'attente' ? 'attente' : 'eligibles',
            'session' => SessionParrainage::find($request->integer('session_id')),
            'annee' => preg_match('/^\d{4}-\d{4}$/', $annee) ? $annee : SessionParrainageController::anneeScolaireCourante(),
            'nature' => $natureId ? NatureAppui::find($natureId) : null,
            'zone_parrain' => $premierAffichage || $request->boolean('zone_parrain'),
            // Localités ajoutées à la zone du parrain, pour compléter la liste
            'regions' => array_values(array_filter(array_map('intval', (array) $request->input('regions', [])))),
            'provinces' => array_values(array_filter(array_map('intval', (array) $request->input('provinces', [])))),
            'sexe' => $plusieurs('sexe', Oev::SEXES),
            'statut' => $plusieurs('statut', Oev::STATUTS),
            'vulnerabilite' => $plusieurs('vulnerabilite', Oev::VULNERABILITES),
            'niveau_priorite' => $plusieurs('niveau_priorite', Oev::NIVEAUX),
            'niveau_etude' => $plusieurs('niveau_etude', Oev::NIVEAUX_ETUDE),
            'handicap' => $plusieurs('handicap', ['1' => 1, '0' => 1]),
            'age_min' => is_numeric($request->input('age_min')) ? max(0, min(25, (int) $request->input('age_min'))) : null,
            'age_max' => is_numeric($request->input('age_max')) ? max(0, min(25, (int) $request->input('age_max'))) : null,
            'natures_parrain' => $preferences['natures'],
        ];
    }

    private function peutExtrairePaiement(User $utilisateur): bool
    {
        return in_array($utilisateur->niveau(), [User::NIVEAU_CENTRAL, User::NIVEAU_REGION], true);
    }

    private function libelleFiltres(array $filtres): ?string
    {
        $libelles = array_filter([
            isset($filtres['region_id']) ? 'Région : ' . Region::whereKey($filtres['region_id'])->value('nom') : null,
            isset($filtres['province_id']) ? 'Province : ' . Province::whereKey($filtres['province_id'])->value('nom') : null,
            isset($filtres['commune_id']) ? 'Commune : ' . Commune::whereKey($filtres['commune_id'])->value('nom') : null,
            isset($filtres['etablissement_id']) ? 'Établissement n° ' . $filtres['etablissement_id'] : null,
            isset($filtres['type_appui']) ? 'Type d’appui : ' . SessionParrainage::TYPES_APPUI[$filtres['type_appui']] : null,
        ]);

        return $libelles ? 'Filtres : ' . implode(' · ', $libelles) : null;
    }
}
