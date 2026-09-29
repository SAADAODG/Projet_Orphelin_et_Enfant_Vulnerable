<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $regions = Region::withCount('provinces')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('ancien_nom', 'like', "%{$search}%");
                });
            })
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('localites.regions.index', [
            'regions' => $regions,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('localites.regions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        Region::create($data);

        return redirect()->route('localites.regions.index')
            ->with('success', 'Région créée avec succès.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Region $region): View
    {
        return view('localites.regions.edit', ['region' => $region]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Region $region): RedirectResponse
    {
        $data = $this->validateData($request, $region->id);

        $region->update($data);

        return redirect()->route('localites.regions.index')
            ->with('success', 'Région mise à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Region $region): RedirectResponse
    {
        if ($region->provinces()->exists()) {
            return redirect()->route('localites.regions.index')
                ->with('error', "Impossible de supprimer « {$region->nom} » : des provinces y sont encore rattachées.");
        }

        if ($region->estUtiliseeParDesDossiers()) {
            return redirect()->route('localites.regions.index')
                ->with('error', "Impossible de supprimer « {$region->nom} » : elle est utilisée par des signalements, plaintes ou OEV.");
        }

        $region->delete();

        return redirect()->route('localites.regions.index')
            ->with('success', 'Région supprimée avec succès.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $uniqueNom = Rule::unique('regions', 'nom')->ignore($ignoreId);

        return $request->validate([
            'nom' => ['required', 'string', 'max:255', $uniqueNom],
            'ancien_nom' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
