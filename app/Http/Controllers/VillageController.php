<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Region;
use App\Models\Village;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VillageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $filtres = $request->only(['region_id', 'province_id', 'commune_id']);

        $villages = Village::with('commune.province.region')
            ->when($search !== '', fn ($query) => $query->where('nom', 'like', "%{$search}%"))
            ->when($filtres['commune_id'] ?? null, fn ($query, $id) => $query->where('commune_id', $id))
            ->when(! ($filtres['commune_id'] ?? null) && ($filtres['province_id'] ?? null),
                fn ($query) => $query->whereHas('commune', fn ($q) => $q->where('province_id', $filtres['province_id'])))
            ->when(! ($filtres['province_id'] ?? null) && ($filtres['region_id'] ?? null),
                fn ($query) => $query->whereHas('commune.province', fn ($q) => $q->where('region_id', $filtres['region_id'])))
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('localites.villages.index', [
            'villages' => $villages,
            'search' => $search,
            'filtres' => $filtres,
            'localites' => Region::arborescence(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        // Commune pré-remplie pour enchaîner plusieurs saisies dans la même commune
        $commune = Commune::with('province')->find($request->query('commune_id'));

        return view('localites.villages.create', [
            'village' => new Village(['commune_id' => $commune?->id]),
            'valeurs' => ['region_id' => $commune?->province->region_id, 'province_id' => $commune?->province_id, 'commune_id' => $commune?->id],
            'localites' => Region::arborescence(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $village = Village::create($this->validateData($request));

        return redirect()->route('localites.villages.index', ['commune_id' => $village->commune_id])
            ->with('success', "Village « {$village->nom} » créé avec succès.");
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Village $village): View
    {
        $village->load('commune.province');

        return view('localites.villages.edit', [
            'village' => $village,
            'valeurs' => ['region_id' => $village->commune->province->region_id, 'province_id' => $village->commune->province_id, 'commune_id' => $village->commune_id],
            'localites' => Region::arborescence(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Village $village): RedirectResponse
    {
        $village->update($this->validateData($request, $village->id));

        return redirect()->route('localites.villages.index')
            ->with('success', 'Village mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Village $village): RedirectResponse
    {
        if ($village->estUtiliseeParDesDossiers()) {
            return redirect()->route('localites.villages.index')
                ->with('error', "Impossible de supprimer « {$village->nom} » : il est utilisé par des dossiers OEV.");
        }

        $village->delete();

        return redirect()->route('localites.villages.index')
            ->with('success', 'Village supprimé avec succès.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $uniqueNom = Rule::unique('villages', 'nom')
            ->where(fn ($query) => $query->where('commune_id', $request->input('commune_id')));

        if ($ignoreId) {
            $uniqueNom = $uniqueNom->ignore($ignoreId);
        }

        return $request->validate([
            'commune_id' => ['required', 'integer', Rule::exists('communes', 'id')->where('province_id', $request->input('province_id'))],
            'nom' => ['required', 'string', 'max:255', $uniqueNom],
        ], [
            'commune_id.required' => 'Choisissez la commune du village.',
            'commune_id.exists' => 'La commune choisie n’appartient pas à la province sélectionnée.',
            'nom.required' => 'Saisissez le nom du village.',
            'nom.unique' => 'Ce village existe déjà dans cette commune.',
        ]);
    }
}
