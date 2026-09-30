<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommuneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $regionId = $request->query('region_id');
        $provinceId = $request->query('province_id');

        $communes = Commune::with('province.region')
            ->when($search !== '', fn ($query) => $query->where('nom', 'like', "%{$search}%"))
            ->when($provinceId, fn ($query) => $query->where('province_id', $provinceId))
            ->when($regionId && ! $provinceId, function ($query) use ($regionId) {
                $query->whereHas('province', fn ($q) => $q->where('region_id', $regionId));
            })
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('localites.communes.index', [
            'communes' => $communes,
            'search' => $search,
            'regionId' => $regionId,
            'provinceId' => $provinceId,
            'regions' => Region::orderBy('nom')->get(),
            'provinces' => Province::orderBy('nom')->get(['id', 'nom', 'region_id']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('localites.communes.create', [
            'regions' => Region::orderBy('nom')->get(),
            'provinces' => Province::orderBy('nom')->get(['id', 'nom', 'region_id']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        Commune::create($data);

        return redirect()->route('localites.communes.index')
            ->with('success', 'Commune créée avec succès.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Commune $commune): View
    {
        return view('localites.communes.edit', [
            'commune' => $commune,
            'regions' => Region::orderBy('nom')->get(),
            'provinces' => Province::orderBy('nom')->get(['id', 'nom', 'region_id']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Commune $commune): RedirectResponse
    {
        $data = $this->validateData($request, $commune->id);

        $commune->update($data);

        return redirect()->route('localites.communes.index')
            ->with('success', 'Commune mise à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Commune $commune): RedirectResponse
    {
        if ($commune->estUtiliseeParDesDossiers()) {
            return redirect()->route('localites.communes.index')
                ->with('error', "Impossible de supprimer « {$commune->nom} » : elle est utilisée par des signalements, plaintes ou OEV.");
        }

        if ($commune->aDesVillages()) {
            return redirect()->route('localites.communes.index')
                ->with('error', "Impossible de supprimer « {$commune->nom} » : des villages y sont rattachés.");
        }

        $commune->delete();

        return redirect()->route('localites.communes.index')
            ->with('success', 'Commune supprimée avec succès.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $provinceId = $request->input('province_id');

        $uniqueNom = Rule::unique('communes', 'nom')
            ->where(fn ($query) => $query->where('province_id', $provinceId));

        if ($ignoreId) {
            $uniqueNom = $uniqueNom->ignore($ignoreId);
        }

        return $request->validate([
            'province_id' => ['required', 'exists:provinces,id'],
            'nom' => ['required', 'string', 'max:255', $uniqueNom],
        ]);
    }
}
