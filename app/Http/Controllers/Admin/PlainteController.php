<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plainte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlainteController extends Controller
{
    /**
     * Liste des plaintes, filtrable par statut.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'statut' => ['nullable', Rule::in(array_keys(Plainte::STATUTS))],
        ]);

        $statut = $request->query('statut');

        $plaintes = Plainte::with(['province', 'commune'])
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->query('q').'%';
                $query->where(fn ($sub) => $sub
                    ->where('reference', 'ilike', $q)
                    ->orWhere('description', 'ilike', $q)
                    ->orWhere('localite', 'ilike', $q)
                    ->ouLocaliteContient($q, 'ilike'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $compteurs = Plainte::query()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return view('admin.plaintes.index', [
            'plaintes' => $plaintes,
            'statut' => $statut,
            'compteurs' => $compteurs,
            'total' => $compteurs->sum(),
        ]);
    }

    /**
     * Détail d'une plainte. L'ouverture la marque comme lue.
     */
    public function show(Plainte $plainte): View
    {
        if (! $plainte->lu_at) {
            $plainte->forceFill(['lu_at' => now()])->save();
        }

        return view('admin.plaintes.show', ['plainte' => $plainte]);
    }

    public function statut(Request $request, Plainte $plainte): RedirectResponse
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(array_keys(Plainte::STATUTS))],
        ]);

        $plainte->forceFill(['statut' => $data['statut']])->save();

        return redirect()->route('admin.plaintes.show', $plainte)
            ->with('success', 'Statut mis à jour : '.$plainte->statut_libelle.'.');
    }
}
