<?php

namespace App\Http\Controllers;

use App\Models\AppuiPartenaire;
use App\Models\EngagementParrain;
use App\Models\JournalActivite;
use App\Models\NatureAppui;
use App\Models\Parrain;
use App\Models\Province;
use App\Models\Region;
use App\Support\Montant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module « Parrains », vue « Répertoire des parrains » : le niveau central gère le répertoire
 * (identité, zone d'intervention, engagements) ; les autres le consultent.
 */
class ParrainController extends Controller
{
    public function index(Request $request): View
    {
        $filtres = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'region_id' => $request->integer('region_id') ?: null,
            'actif' => (string) $request->query('actif', ''),
        ];
        $region = $filtres['region_id'] ? Region::find($filtres['region_id']) : null;

        $parrains = Parrain::with(['zones.region', 'zones.province'])
            ->withCount(['appuis as oev_appuyes_count' => fn ($q) => $q->select(DB::raw('count(distinct oev_id)'))])
            ->when($filtres['q'] !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$filtres['q']}%")
                ->orWhere('contact_nom', 'like', "%{$filtres['q']}%")))
            ->when(array_key_exists($filtres['type'], Parrain::TYPES), fn ($q) => $q->where('type', $filtres['type']))
            ->when($region, fn ($q) => $q->intervenantDans($region))
            ->when(in_array($filtres['actif'], ['1', '0'], true), fn ($q) => $q->where('actif', $filtres['actif'] === '1'))
            ->orderByDesc('actif')
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('parrainage.parrains.index', [
            'parrains' => $parrains,
            'filtres' => $filtres,
            'regions' => Region::orderBy('nom')->pluck('nom', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('parrainage.parrains.create', $this->donneesFormulaire(new Parrain(['actif' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);

        $parrain = DB::transaction(function () use ($request, $donnees) {
            $parrain = Parrain::create($donnees['parrain'] + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $this->enregistrerZones($parrain, $donnees);
            JournalActivite::consigner('parrain.creation', $parrain, "Création du parrain {$parrain->nom}");

            return $parrain;
        });

        return redirect()->route('parrainage.parrains.show', $parrain)->with('success', "Parrain « {$parrain->nom} » enregistré.");
    }

    public function show(Request $request, Parrain $parrain): View
    {
        $parrain->load(['zones.region', 'zones.province', 'engagements']);
        // Statistiques limitées aux OEV de la zone de l'utilisateur
        $appuis = AppuiPartenaire::where('parrain_id', $parrain->id)->dansLePerimetreDe($request->user());

        return view('parrainage.parrains.show', [
            'parrain' => $parrain,
            'oevAppuyes' => (clone $appuis)->distinct()->count('oev_id'),
            'parAnnee' => (clone $appuis)
                ->selectRaw('annee, count(distinct oev_id) as oev, count(*) as appuis, coalesce(sum(montant), 0) as montant')
                ->groupBy('annee')
                ->orderByDesc('annee')
                ->get(),
            'natures' => NatureAppui::actives()->get(),
            'journal' => JournalActivite::de($parrain)->with('utilisateur')->limit(10)->get(),
        ]);
    }

    public function edit(Parrain $parrain): View
    {
        return view('parrainage.parrains.edit', $this->donneesFormulaire($parrain->load('zones')));
    }

    public function update(Request $request, Parrain $parrain): RedirectResponse
    {
        $donnees = $this->valider($request);

        DB::transaction(function () use ($request, $parrain, $donnees) {
            $etaitActif = $parrain->actif;
            $parrain->update($donnees['parrain'] + ['updated_by' => $request->user()->id]);
            $this->enregistrerZones($parrain, $donnees);

            $description = match (true) {
                $etaitActif && ! $parrain->actif => "Désactivation du parrain {$parrain->nom}",
                ! $etaitActif && $parrain->actif => "Réactivation du parrain {$parrain->nom}",
                default => "Modification du parrain {$parrain->nom}",
            };
            JournalActivite::consigner('parrain.modification', $parrain, $description, [
                'champs' => array_values(array_diff(array_keys($parrain->getChanges()), ['updated_at', 'updated_by'])),
            ]);
        });

        return redirect()->route('parrainage.parrains.show', $parrain)->with('success', 'Parrain mis à jour.');
    }

    public function destroy(Parrain $parrain): RedirectResponse
    {
        if (! $parrain->estSupprimable()) {
            return back()->with('error', "« {$parrain->nom} » a des appuis ou des contributions : désactivez-le au lieu de le supprimer.");
        }

        DB::transaction(function () use ($parrain) {
            foreach ($parrain->engagements as $engagement) {
                $this->supprimerConvention($engagement);
            }
            JournalActivite::consigner('parrain.suppression', null, "Suppression du parrain {$parrain->nom}", ['parrain_id' => $parrain->id]);
            $parrain->delete();
        });

        return redirect()->route('parrainage.parrains.index')->with('success', 'Parrain supprimé.');
    }

    public function storeEngagement(Request $request, Parrain $parrain): RedirectResponse
    {
        $request->merge(['montant_prevu' => Montant::normaliserSaisie($request->input('montant_prevu'))]);
        $donnees = $request->validateWithBag('engagement', [
            'date_engagement' => ['required', 'date'],
            'duree_mois' => ['nullable', 'integer', 'min:1', 'max:600'],
            'natures_appui' => ['nullable', 'array'],
            'natures_appui.*' => ['integer', Rule::exists('natures_appui', 'id')],
            'nombre_oev_prevu' => ['nullable', 'integer', 'min:1'],
            'montant_prevu' => ['nullable', 'integer', 'min:0'],
            'convention' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'convention.mimes' => 'La convention doit être un fichier PDF, Word ou une image.',
            'convention.max' => 'La convention ne doit pas dépasser 5 Mo.',
        ]);

        $engagement = $parrain->engagements()->create(collect($donnees)->except('convention')->all() + [
            'natures_appui' => array_map('intval', $donnees['natures_appui'] ?? []),
            'created_by' => $request->user()->id,
        ]);
        if ($request->hasFile('convention')) {
            $fichier = $request->file('convention');
            $engagement->update([
                'convention_chemin' => $fichier->store("parrains/{$parrain->id}", 'local'),
                'convention_nom' => $fichier->getClientOriginalName(),
            ]);
        }

        JournalActivite::consigner('parrain.engagement', $parrain, "Engagement du {$engagement->date_engagement->format('d/m/Y')} ajouté");

        return redirect()->route('parrainage.parrains.show', $parrain)->with('success', 'Engagement enregistré.');
    }

    public function destroyEngagement(Parrain $parrain, EngagementParrain $engagement): RedirectResponse
    {
        abort_unless($engagement->parrain_id === $parrain->id, 404);

        $this->supprimerConvention($engagement);
        $engagement->delete();
        JournalActivite::consigner('parrain.engagement_suppression', $parrain, "Engagement du {$engagement->date_engagement->format('d/m/Y')} supprimé");

        return redirect()->route('parrainage.parrains.show', $parrain)->with('success', 'Engagement supprimé.');
    }

    public function convention(Parrain $parrain, EngagementParrain $engagement): StreamedResponse
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disque */
        $disque = Storage::disk('local');
        abort_unless($engagement->parrain_id === $parrain->id && $engagement->convention_chemin && $disque->exists($engagement->convention_chemin), 404);

        return $disque->response($engagement->convention_chemin, $engagement->convention_nom);
    }

    private function donneesFormulaire(Parrain $parrain): array
    {
        return [
            'parrain' => $parrain,
            // Région > provinces, pour les listes déroulantes en cascade de la zone d'intervention
            'regions' => Region::with(['provinces' => fn ($q) => $q->orderBy('nom')->select(['id', 'nom', 'region_id'])])->orderBy('nom')->get(['id', 'nom']),
            'zones' => $parrain->zones->map(fn ($zone) => ['region_id' => $zone->region_id, 'province_id' => $zone->province_id])->values()->all(),
        ];
    }

    /**
     * Zone d'intervention : des lignes « région + province facultative » (zones[n][region_id], zones[n][province_id]).
     * Sans province, toute la région est couverte.
     *
     * @return array{parrain: array, regions: array<int>, provinces: array<int>}
     */
    private function valider(Request $request): array
    {
        // Lignes laissées vides (aucune région choisie) ignorées
        $request->merge(['zones' => array_values(array_filter((array) $request->input('zones', []), fn ($zone) => ! empty($zone['region_id'])))]);

        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(Parrain::TYPES))],
            'nom' => ['required', 'string', 'max:200'],
            'contact_nom' => ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'zones' => ['array'],
            'zones.*.region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
            'zones.*.province_id' => ['nullable', 'integer', function (string $attribut, $valeur, \Closure $echec) use ($request) {
                $regionId = data_get($request->input(), str_replace('province_id', 'region_id', $attribut));
                if (! Province::whereKey($valeur)->where('region_id', $regionId)->exists()) {
                    $echec('La province choisie n’appartient pas à la région.');
                }
            }],
        ], [
            'nom.required' => 'Indiquez le nom ou la raison sociale du parrain.',
            'zones.*.region_id.exists' => 'Région inconnue.',
        ]);

        $zones = collect($donnees['zones'] ?? []);

        return [
            'parrain' => collect($donnees)->only(['type', 'nom', 'contact_nom', 'telephone', 'email', 'adresse'])->all()
                + ['actif' => $request->boolean('actif')],
            'regions' => $zones->whereNull('province_id')->pluck('region_id')->map(fn ($id) => (int) $id)->all(),
            'provinces' => $zones->whereNotNull('province_id')->pluck('province_id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    private function enregistrerZones(Parrain $parrain, array $donnees): void
    {
        $parrain->zones()->delete();
        foreach (array_unique($donnees['regions']) as $regionId) {
            $parrain->zones()->create(['region_id' => $regionId]);
        }
        // Une province dont toute la région est déjà choisie est redondante
        $provinces = Province::whereIn('id', array_unique($donnees['provinces']))->whereNotIn('region_id', $donnees['regions'])->get(['id', 'region_id']);
        foreach ($provinces as $province) {
            $parrain->zones()->create(['region_id' => $province->region_id, 'province_id' => $province->id]);
        }
    }

    private function supprimerConvention(EngagementParrain $engagement): void
    {
        if ($engagement->convention_chemin) {
            Storage::disk('local')->delete($engagement->convention_chemin);
        }
    }
}
