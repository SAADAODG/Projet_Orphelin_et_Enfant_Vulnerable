<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Ecole;
use App\Models\Region;
use App\Services\Referentiel\ImportEcoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Paramètres > Écoles : référentiel des écoles par commune, proposé dans le formulaire OEV
 * (établissement fréquenté). Alimenté par EcoleSeeder ou par l'import du fichier Excel du ministère.
 */
class EcoleController extends Controller
{
    /** Colonnes attendues dans le fichier importé, dans cet ordre (ligne 1 = en-tête). */
    public const COLONNES_IMPORT = ['Région', 'Province', 'Commune', 'CEB', 'École', 'Statut', 'Type'];

    /** Écoles d'une commune, chargées à la demande par le formulaire OEV (tout agent connecté). */
    public function parCommune(Commune $commune): JsonResponse
    {
        return response()->json($commune->ecoles()->orderBy('ceb')->orderBy('nom')->get(['id', 'nom', 'ceb', 'statut', 'type']));
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $filtres = $request->only(['region_id', 'province_id', 'commune_id', 'statut', 'type']);

        $ecoles = Ecole::with('commune.province.region')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('nom', 'like', "%{$search}%")->orWhere('ceb', 'like', "%{$search}%")))
            ->when($filtres['commune_id'] ?? null, fn ($query, $id) => $query->where('commune_id', $id))
            ->when(! ($filtres['commune_id'] ?? null) && ($filtres['province_id'] ?? null),
                fn ($query) => $query->whereHas('commune', fn ($q) => $q->where('province_id', $filtres['province_id'])))
            ->when(! ($filtres['province_id'] ?? null) && ($filtres['region_id'] ?? null),
                fn ($query) => $query->whereHas('commune.province', fn ($q) => $q->where('region_id', $filtres['region_id'])))
            ->when($filtres['statut'] ?? null, fn ($query, $statut) => $query->where('statut', $statut))
            ->when($filtres['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderBy('nom')
            ->paginate(20)
            ->withQueryString();

        return view('parametres.ecoles.index', [
            'ecoles' => $ecoles,
            'search' => $search,
            'filtres' => $filtres,
            'localites' => Region::arborescence(),
        ]);
    }

    public function create(Request $request): View
    {
        // Commune pré-remplie pour enchaîner plusieurs saisies dans la même commune
        $commune = Commune::with('province')->find($request->query('commune_id'));

        return view('parametres.ecoles.create', [
            'ecole' => new Ecole(['commune_id' => $commune?->id]),
            'valeurs' => ['region_id' => $commune?->province->region_id, 'province_id' => $commune?->province_id, 'commune_id' => $commune?->id],
            'localites' => Region::arborescence(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ecole = Ecole::create($this->validateData($request));

        return redirect()->route('parametres.ecoles.index', ['commune_id' => $ecole->commune_id])
            ->with('success', "École « {$ecole->nom} » créée avec succès.");
    }

    public function edit(Ecole $ecole): View
    {
        $ecole->load('commune.province');

        return view('parametres.ecoles.edit', [
            'ecole' => $ecole,
            'valeurs' => ['region_id' => $ecole->commune->province->region_id, 'province_id' => $ecole->commune->province_id, 'commune_id' => $ecole->commune_id],
            'localites' => Region::arborescence(),
        ]);
    }

    public function update(Request $request, Ecole $ecole): RedirectResponse
    {
        $ecole->update($this->validateData($request, $ecole->id));

        return redirect()->route('parametres.ecoles.index', ['commune_id' => $ecole->commune_id])
            ->with('success', 'École mise à jour avec succès.');
    }

    public function destroy(Ecole $ecole): RedirectResponse
    {
        if ($ecole->estUtiliseeParDesDossiers()) {
            return redirect()->route('parametres.ecoles.index')
                ->with('error', "Impossible de supprimer « {$ecole->nom} » : elle est choisie par des dossiers OEV.");
        }

        $ecole->delete();

        return redirect()->route('parametres.ecoles.index')
            ->with('success', 'École supprimée avec succès.');
    }

    /** Import du fichier Excel des écoles : ajoute les nouvelles, met à jour le statut et le type des autres. */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(
            ['fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']],
            ['fichier.required' => 'Choisissez le fichier Excel à importer.', 'fichier.mimes' => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.'],
        );

        try {
            $lecteur = IOFactory::createReaderForFile($request->file('fichier')->getRealPath());
            $lecteur->setReadDataOnly(true);
            $lignes = $lecteur->load($request->file('fichier')->getRealPath())->getSheet(0)->toArray(null, false, false, false);
        } catch (\Throwable $e) {
            return back()->withErrors(['fichier' => 'Le fichier n’a pas pu être lu : vérifiez qu’il s’agit bien d’un fichier Excel.']);
        }

        $rapport = (new ImportEcoles)->importer(array_slice($lignes, 1));

        $message = "{$rapport['ajoutees']} école(s) ajoutée(s), {$rapport['mises_a_jour']} mise(s) à jour, {$rapport['ignorees']} ligne(s) ignorée(s).";
        if ($rapport['communes_introuvables']) {
            $message .= ' Communes introuvables : ' . implode(', ', array_slice($rapport['communes_introuvables'], 0, 15))
                . (count($rapport['communes_introuvables']) > 15 ? '…' : '') . '.';
        }

        return redirect()->route('parametres.ecoles.index')
            ->with($rapport['ajoutees'] + $rapport['mises_a_jour'] > 0 ? 'success' : 'error', $message);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $uniqueNom = Rule::unique('ecoles', 'nom')
            ->where(fn ($query) => $query->where('commune_id', $request->input('commune_id'))->where('ceb', $request->input('ceb')));

        if ($ignoreId) {
            $uniqueNom = $uniqueNom->ignore($ignoreId);
        }

        return $request->validate([
            'commune_id' => ['required', 'integer', Rule::exists('communes', 'id')->where('province_id', $request->input('province_id'))],
            'ceb' => ['nullable', 'string', 'max:150'],
            'nom' => ['required', 'string', 'max:255', $uniqueNom],
            'statut' => ['nullable', Rule::in(array_keys(Ecole::STATUTS))],
            'type' => ['nullable', Rule::in(array_keys(Ecole::TYPES))],
        ], [
            'commune_id.required' => 'Choisissez la commune de l’école.',
            'commune_id.exists' => 'La commune choisie n’appartient pas à la province sélectionnée.',
            'nom.required' => 'Saisissez le nom de l’école.',
            'nom.unique' => 'Cette école existe déjà dans cette commune (même CEB).',
        ]);
    }
}
