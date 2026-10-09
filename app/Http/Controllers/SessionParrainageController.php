<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\Parrain;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Services\Parrainage\ParrainageService;
use App\Services\Parrainage\SourceSelection;
use App\Services\Parrainage\TransitionSessionInvalide;
use App\Support\Montant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Module « Sessions » du parrainage : le niveau central ouvre une session (enveloppe, plafond,
 * financement, options RG-04 / RG-07), répartit les quotas régionaux et la fait avancer jusqu'à
 * la clôture. Les DR et DP consultent.
 */
class SessionParrainageController extends Controller
{
    public function __construct(
        private readonly ParrainageService $parrainage,
        private readonly SourceSelection $sourceSelection,
    ) {
    }

    public function index(Request $request): View
    {
        $filtres = [
            'annee' => (string) $request->query('annee', ''),
            'type_appui' => (string) $request->query('type_appui', ''),
            'etat' => (string) $request->query('etat', ''),
        ];

        $sessions = SessionParrainage::query()
            ->when($filtres['annee'] !== '', fn ($q) => $q->where('annee', $filtres['annee']))
            ->when(array_key_exists($filtres['type_appui'], SessionParrainage::TYPES_APPUI), fn ($q) => $q->where('type_appui', $filtres['type_appui']))
            ->when(array_key_exists($filtres['etat'], SessionParrainage::ETATS), fn ($q) => $q->where('etat', $filtres['etat']))
            ->orderByDesc('annee')
            ->orderBy('numero')
            ->paginate(15)
            ->withQueryString();

        return view('parrainage.sessions.index', [
            'sessions' => $sessions,
            'filtres' => $filtres,
            'annees' => SessionParrainage::distinct()->orderByDesc('annee')->pluck('annee'),
        ]);
    }

    public function create(): View
    {
        $annee = self::anneeScolaireCourante();

        return view('parrainage.sessions.create', $this->donneesFormulaire(new SessionParrainage([
            'annee' => $annee,
            'numero' => (int) SessionParrainage::where('annee', $annee)->max('numero') + 1,
            'type_appui' => SessionParrainage::TYPE_SCOLAIRE,
            'source_financement' => SessionParrainage::SOURCE_ETAT,
            'date_ouverture' => now(),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);

        $session = DB::transaction(function () use ($request, $donnees) {
            $session = SessionParrainage::create($donnees['session'] + [
                'etat' => SessionParrainage::ETAT_EN_COURS,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $session->parrains()->sync($donnees['contributions']);

            JournalActivite::consigner('session_parrainage.creation', $session, "Création de la session {$session->reference()}", [
                'enveloppe' => $session->enveloppe,
                'plafond_beneficiaire' => $session->plafond_beneficiaire,
            ]);

            return $session;
        });

        return redirect()->route('parrainage.sessions.show', $session)
            ->with('success', "Session {$session->reference()} ouverte.");
    }

    public function show(Request $request, SessionParrainage $session): View
    {
        $session->load(['quotas', 'parrains', 'sessionOrigine', 'createur']);
        $vue = $request->query('vue') === 'quotas' && $session->quotas_actifs ? 'quotas' : 'informations';
        $gere = $request->user()->can('gérer sessions parrainage');

        // « Répartir au prorata » : proposition affichée dans le formulaire, enregistrée seulement si l'agent valide
        $prorata = $vue === 'quotas' && $request->boolean('prorata') && $gere && $session->parametresModifiables();

        $origine = $session->sessionOrigine;
        $engageOrigine = $origine ? $this->sourceSelection->montantEngage($origine) : null;

        return view('parrainage.sessions.show', [
            'session' => $session,
            'vue' => $vue,
            'gere' => $gere,
            'regions' => Region::orderBy('nom')->get(['id', 'nom']),
            'quotas' => $prorata
                ? $this->parrainage->proposerQuotasAuProrata($session)
                : $session->quotas->pluck('montant', 'region_id')->all(),
            'prorata' => $prorata,
            'eligibles' => $vue === 'quotas' ? $this->sourceSelection->eligiblesParRegion($session) : [],
            'resteOrigine' => $engageOrigine === null ? null : $origine->enveloppe - $engageOrigine,
            'journal' => JournalActivite::de($session)->with('utilisateur')->limit(20)->get(),
        ]);
    }

    public function edit(SessionParrainage $session): View|RedirectResponse
    {
        if (! $session->estModifiable()) {
            return redirect()->route('parrainage.sessions.show', $session)
                ->with('error', 'Cette session est clôturée : elle ne peut plus être modifiée.');
        }

        return view('parrainage.sessions.edit', $this->donneesFormulaire($session->load('parrains')));
    }

    public function update(Request $request, SessionParrainage $session): RedirectResponse
    {
        if (! $session->estModifiable()) {
            return redirect()->route('parrainage.sessions.show', $session)
                ->with('error', 'Cette session est clôturée : elle ne peut plus être modifiée.');
        }

        // Session validée : enveloppe, plafond, financement et options sont figés, seule la description change
        if (! $session->parametresModifiables()) {
            $donnees = $request->validate(['description' => ['required', 'string', 'max:255']]);
            $session->update($donnees + ['updated_by' => $request->user()->id]);
            JournalActivite::consigner('session_parrainage.modification', $session, "Modification de la session {$session->reference()}", [
                'champs' => array_keys($session->getChanges()),
            ]);

            return redirect()->route('parrainage.sessions.show', $session)->with('success', 'Session mise à jour.');
        }

        $donnees = $this->valider($request, $session);

        DB::transaction(function () use ($request, $session, $donnees) {
            $session->update($donnees['session'] + ['updated_by' => $request->user()->id]);
            $session->parrains()->sync($donnees['contributions']);

            JournalActivite::consigner('session_parrainage.modification', $session, "Modification de la session {$session->reference()}", [
                'champs' => array_values(array_diff(array_keys($session->getChanges()), ['updated_at', 'updated_by'])),
            ]);
        });

        return redirect()->route('parrainage.sessions.show', $session)->with('success', 'Session mise à jour.');
    }

    /** Vue « Quotas » : un montant par région, total ≤ enveloppe (RG-04). Les régions à 0 n'ont pas de quota. */
    public function updateQuotas(Request $request, SessionParrainage $session): RedirectResponse
    {
        $retour = redirect()->route('parrainage.sessions.show', ['session' => $session, 'vue' => 'quotas']);

        if (! $session->quotas_actifs) {
            return $retour->with('error', 'Les quotas par région ne sont pas activés pour cette session.');
        }
        if (! $session->parametresModifiables()) {
            return $retour->with('error', "La session est « {$session->libelleEtat()} » : les quotas sont figés.");
        }

        $request->merge(['quotas' => array_map(self::normaliserMontant(...), (array) $request->input('quotas', []))]);
        $regions = Region::pluck('id')->all();
        $validated = $request->validate([
            'quotas' => ['required', 'array'],
            'quotas.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'quotas.*.integer' => 'Chaque quota doit être un montant entier en FCFA.',
            'quotas.*.min' => 'Un quota ne peut pas être négatif.',
        ]);

        $quotas = collect($validated['quotas'])->only($regions)->map(fn ($m) => (int) $m);
        if ($quotas->sum() > $session->enveloppe) {
            return back()->withInput()->withErrors(['quotas' => sprintf(
                'Le total des quotas (%s) dépasse l’enveloppe de la session (%s).',
                Montant::fcfa($quotas->sum()),
                Montant::fcfa($session->enveloppe),
            )]);
        }

        DB::transaction(function () use ($session, $quotas) {
            $session->quotas()->whereNotIn('region_id', $quotas->filter()->keys())->delete();
            foreach ($quotas->filter() as $regionId => $montant) {
                $session->quotas()->updateOrCreate(['region_id' => $regionId], ['montant' => $montant]);
            }

            JournalActivite::consigner('session_parrainage.quotas', $session, 'Mise à jour des quotas par région', [
                'total' => $quotas->sum(),
                'quotas' => $quotas->filter()->all(),
            ]);
        });

        return $retour->with('success', 'Quotas enregistrés.');
    }

    public function changerEtat(Request $request, SessionParrainage $session): RedirectResponse
    {
        $donnees = $request->validate([
            'etat' => ['required', Rule::in(array_keys(SessionParrainage::ETATS))],
            'motif' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $session = $this->parrainage->changerEtatSession($session->id, $donnees['etat'], $request->user(), $donnees['motif'] ?? null);
        } catch (TransitionSessionInvalide $e) {
            return back()->withInput()->withErrors(['etat' => $e->getMessage()]);
        }

        return redirect()->route('parrainage.sessions.show', $session)
            ->with('success', "La session est maintenant « {$session->libelleEtat()} ».");
    }

    private function donneesFormulaire(SessionParrainage $session): array
    {
        return [
            'session' => $session,
            // Parrains proposés : les actifs, plus ceux qui contribuent déjà à la session
            'parrains' => Parrain::query()
                ->where(fn ($q) => $q->where('actif', true)->orWhereIn('id', $session->parrains->pluck('id')))
                ->orderBy('nom')
                ->get(),
            'contributions' => $session->parrains->pluck('pivot.montant', 'id')->all(),
            'sessionsOrigine' => SessionParrainage::whereKeyNot($session->id)->orderByDesc('annee')->orderBy('numero')->get(),
        ];
    }

    /**
     * @return array{session: array, contributions: array<int, array{montant: int}>}
     */
    private function valider(Request $request, ?SessionParrainage $session = null): array
    {
        $request->merge([
            'enveloppe' => self::normaliserMontant($request->input('enveloppe')),
            'plafond_beneficiaire' => self::normaliserMontant($request->input('plafond_beneficiaire')),
            'contributions' => array_map(self::normaliserMontant(...), (array) $request->input('contributions', [])),
        ]);

        $annee = (string) $request->input('annee');
        $avecParrains = in_array($request->input('source_financement'), SessionParrainage::SOURCES_AVEC_PARRAINS, true);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'annee' => ['required', 'regex:/^\d{4}-\d{4}$/', function (string $attribut, $valeur, \Closure $echec) {
                [$debut, $fin] = array_map('intval', explode('-', (string) $valeur) + [1 => 0]);
                if ($fin !== $debut + 1) {
                    $echec('L’année scolaire doit couvrir deux années consécutives (ex : 2026-2027).');
                }
            }],
            'numero' => ['required', 'integer', 'min:1', 'max:99',
                Rule::unique('sessions_parrainage', 'numero')->where('annee', $annee)->ignore($session?->id)],
            'type_appui' => ['required', Rule::in(array_keys(SessionParrainage::TYPES_APPUI))],
            'enveloppe' => ['required', 'integer', 'min:1', function (string $attribut, $valeur, \Closure $echec) use ($session, $request) {
                if ($session && $request->boolean('quotas_actifs') && (int) $valeur < $session->quotas()->sum('montant')) {
                    $echec('L’enveloppe ne peut pas être inférieure au total des quotas déjà répartis (' . Montant::fcfa($session->quotas()->sum('montant')) . ').');
                }
            }],
            'plafond_beneficiaire' => ['required', 'integer', 'min:1', 'lte:enveloppe'],
            'source_financement' => ['required', Rule::in(array_keys(SessionParrainage::SOURCES_FINANCEMENT))],
            'date_ouverture' => ['required', 'date'],
            'session_origine_id' => ['nullable', 'integer', Rule::exists('sessions_parrainage', 'id')->where('annee', $annee),
                Rule::notIn(array_filter([$session?->id]))],
            'contributions' => [Rule::requiredIf($avecParrains), 'array', function (string $attribut, $valeur, \Closure $echec) use ($avecParrains, $request) {
                $total = array_sum(array_map('intval', array_filter((array) $valeur)));
                if ($avecParrains && $total === 0) {
                    $echec('Indiquez la contribution d’au moins un parrain pour un financement partenaire ou mixte.');
                } elseif ($avecParrains && $total > (int) $request->input('enveloppe')) {
                    $echec('Le total des contributions (' . Montant::fcfa($total) . ') dépasse l’enveloppe.');
                }
            }],
            'contributions.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'annee.regex' => 'L’année scolaire s’écrit sous la forme 2026-2027.',
            'numero.unique' => 'Une session porte déjà ce numéro pour cette année scolaire.',
            'enveloppe.min' => 'L’enveloppe doit être strictement positive.',
            'plafond_beneficiaire.lte' => 'Le plafond par bénéficiaire ne peut pas dépasser l’enveloppe.',
            'session_origine_id.exists' => 'La session d’origine doit appartenir à la même année scolaire.',
            'session_origine_id.not_in' => 'Une session ne peut pas être sa propre session d’origine.',
            'contributions.required' => 'Indiquez la contribution d’au moins un parrain pour un financement partenaire ou mixte.',
            'contributions.*.integer' => 'Chaque contribution doit être un montant entier en FCFA.',
        ]);

        // Parrains inconnus ignorés ; aucune contribution pour un financement État
        $contributions = $avecParrains
            ? collect($validated['contributions'] ?? [])
                ->map(fn ($m) => (int) $m)
                ->filter()
                ->only(Parrain::whereIn('id', array_keys($validated['contributions'] ?? []))->pluck('id'))
                ->map(fn (int $montant) => ['montant' => $montant])
                ->all()
            : [];

        return [
            'session' => collect($validated)->except('contributions')->all() + [
                'bloquer_depassement_enveloppe' => $request->boolean('bloquer_depassement_enveloppe'),
                'quotas_actifs' => $request->boolean('quotas_actifs'),
                'exclure_deja_appuyes' => $request->boolean('exclure_deja_appuyes'),
            ],
            'contributions' => $contributions,
        ];
    }

    /** « 1 250 000 » ou « 1.250.000 » → 1250000 ; une saisie vide reste vide. */
    private static function normaliserMontant($valeur): mixed
    {
        if (! is_string($valeur)) {
            return $valeur;
        }
        $chiffres = preg_replace('/[\s.\x{00A0}\x{202F}]|fcfa/iu', '', $valeur);

        return $chiffres === '' ? null : $chiffres;
    }

    /** Année scolaire en cours : à partir de septembre, celle qui commence. */
    public static function anneeScolaireCourante(): string
    {
        $debut = now()->month >= 9 ? now()->year : now()->year - 1;

        return $debut . '-' . ($debut + 1);
    }
}
