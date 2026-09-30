<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProvinceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $regionId = $request->query('region_id');

        $provinces = Province::with('region')
            ->withCount('communes')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('chef_lieu', 'like', "%{$search}%");
                });
            })
            ->when($regionId, fn ($query) => $query->where('region_id', $regionId))
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('localites.provinces.index', [
            'provinces' => $provinces,
            'search' => $search,
            'regionId' => $regionId,
            'regions' => Region::orderBy('nom')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('localites.provinces.create', [
            'regions' => Region::orderBy('nom')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        Province::create($data);

        return redirect()->route('localites.provinces.index')
            ->with('success', 'Province créée avec succès.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Province $province): View
    {
        return view('localites.provinces.edit', [
            'province' => $province,
            'regions' => Region::orderBy('nom')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Province $province): RedirectResponse
    {
        $data = $this->validateData($request, $province->id);

        $province->update($data);

        return redirect()->route('localites.provinces.index')
            ->with('success', 'Province mise à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Province $province): RedirectResponse
    {
        if ($province->communes()->exists()) {
            return redirect()->route('localites.provinces.index')
                ->with('error', "Impossible de supprimer « {$province->nom} » : des communes y sont encore rattachées.");
        }

        if ($province->estUtiliseeParDesDossiers()) {
            return redirect()->route('localites.provinces.index')
                ->with('error', "Impossible de supprimer « {$province->nom} » : elle est utilisée par des signalements, plaintes ou OEV.");
        }

        $province->delete();

        return redirect()->route('localites.provinces.index')
            ->with('success', 'Province supprimée avec succès.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $regionId = $request->input('region_id');

        $uniqueNom = Rule::unique('provinces', 'nom')
            ->where(fn ($query) => $query->where('region_id', $regionId));

        if ($ignoreId) {
            $uniqueNom = $uniqueNom->ignore($ignoreId);
        }

        return $request->validate([
            'region_id' => ['required', 'exists:regions,id'],
            'nom' => ['required', 'string', 'max:255', $uniqueNom],
            'chef_lieu' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
