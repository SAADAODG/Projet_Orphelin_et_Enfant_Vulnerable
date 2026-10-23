<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CloisonneParZone;
use App\Models\AppuiPartenaire;
use App\Models\EngagementParrain;
use App\Models\JournalActivite;
use App\Models\NatureAppui;
use App\Models\Parrain;
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
 * (identité, engagements) ; les autres le consultent. La zone d'intervention se choisit à l'extraction
 * de la liste d'OEV proposée au parrain (Pilotage > Extractions).
 */
class ParrainController extends Controller
{
    use CloisonneParZone;

    public function index(Request $request): View
    {
        $filtres = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'actif' => (string) $request->query('actif', ''),
        ];

        $parrains = Parrain::withCount(['appuis as oev_appuyes_count' => fn ($q) => $q->select(DB::raw('count(distinct oev_id)'))])
            ->when($filtres['q'] !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereLike('nom', "%{$filtres['q']}%")
                ->orWhereLike('contact_nom', "%{$filtres['q']}%")))
            ->when(array_key_exists($filtres['type'], Parrain::TYPES), fn ($q) => $q->where('type', $filtres['type']))
            ->when(in_array($filtres['actif'], ['1', '0'], true), fn ($q) => $q->where('actif', $filtres['actif'] === '1'))
            ->orderByDesc('actif')
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('parrainage.parrains.index', [
            'parrains' => $parrains,
            'filtres' => $filtres,
            // Fenêtre « Nouveau parrain »
            'formulaire' => $request->user()->can('gérer parrains') ? $this->donneesFormulaire(new Parrain(['actif' => true])) : null,
        ]);
    }

    /** La création se fait dans une fenêtre du répertoire. */
    public function create(): RedirectResponse
    {
        return redirect()->route('parrainage.parrains.index', ['nouveau' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);

        $parrain = DB::transaction(function () use ($request, $donnees) {
            $parrain = Parrain::create($donnees + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            JournalActivite::consigner('parrain.creation', $parrain, "Création du parrain {$parrain->nom}");

            return $parrain;
        });

        return redirect()->route('parrainage.parrains.show', $parrain)->with('success', "Parrain « {$parrain->nom} » enregistré.");
    }

    public function show(Request $request, Parrain $parrain): View
    {
        $parrain->load('engagements');
        // Statistiques limitées aux OEV de la zone de l'utilisateur
        $appuis = $this->cloisonner(AppuiPartenaire::where('parrain_id', $parrain->id), 'oev');

        return view('parrainage.parrains.show', [
            'parrain' => $parrain,
            'oevAppuyes' => (clone $appuis)->distinct()->count('oev_id'),
            'parAnnee' => (clone $appuis)
                ->selectRaw('annee, count(distinct oev_id) as oev, count(*) as appuis, coalesce(sum(montant), 0) as montant')
                ->groupBy('annee')
                ->orderByDesc('annee')
                ->get(),
            'natures' => NatureAppui::actives()->get(),
        ]);
    }

    public function edit(Parrain $parrain): View
    {
        return view('parrainage.parrains.edit', $this->donneesFormulaire($parrain));
    }

    public function update(Request $request, Parrain $parrain): RedirectResponse
    {
        $donnees = $this->valider($request);

        DB::transaction(function () use ($request, $parrain, $donnees) {
            $etaitActif = $parrain->actif;
            $parrain->update($donnees + ['updated_by' => $request->user()->id]);

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
        return ['parrain' => $parrain];
    }

    private function valider(Request $request): array
    {
        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(Parrain::TYPES))],
            'nom' => ['required', 'string', 'max:200'],
            'contact_nom' => ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
        ], [
            'nom.required' => 'Indiquez le nom ou la raison sociale du parrain.',
        ]);

        return $donnees + ['actif' => $request->boolean('actif')];
    }

    private function supprimerConvention(EngagementParrain $engagement): void
    {
        if ($engagement->convention_chemin) {
            Storage::disk('local')->delete($engagement->convention_chemin);
        }
    }
}
