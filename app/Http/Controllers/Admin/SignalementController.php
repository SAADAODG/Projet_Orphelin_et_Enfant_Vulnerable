<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SignalementController extends Controller
{
    /**
     * Liste des signalements, filtrable par statut.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'statut' => ['nullable', Rule::in(array_keys(Signalement::STATUTS))],
        ]);

        $statut = $request->query('statut');

        $signalements = Signalement::query()
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->query('q').'%';
                $query->where(fn ($sub) => $sub
                    ->where('recepisse', 'ilike', $q)
                    ->orWhere('enfant_nom', 'ilike', $q)
                    ->orWhere('enfant_prenom', 'ilike', $q)
                    ->orWhere('declarant_nom', 'ilike', $q)
                    ->orWhere('province', 'ilike', $q));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $compteurs = Signalement::query()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return view('admin.signalements.index', [
            'signalements' => $signalements,
            'statut' => $statut,
            'compteurs' => $compteurs,
            'total' => $compteurs->sum(),
        ]);
    }

    /**
     * Détail d'un signalement. L'ouverture le marque comme lu.
     */
    public function show(Signalement $signalement): View
    {
        if (! $signalement->lu_at) {
            $signalement->forceFill(['lu_at' => now()])->save();
        }

        return view('admin.signalements.show', ['signalement' => $signalement]);
    }

    public function valider(Signalement $signalement): RedirectResponse
    {
        abort_unless($signalement->statut === Signalement::EN_ATTENTE, 422, 'Ce signalement a déjà été traité.');

        $signalement->forceFill([
            'statut' => Signalement::VALIDE,
            'motif_rejet' => null,
            'traite_le' => now(),
        ])->save();

        return redirect()->route('admin.signalements.show', $signalement)
            ->with('success', 'Le signalement a été validé. Le déclarant le verra en suivant son récépissé.');
    }

    public function rejeter(Request $request, Signalement $signalement): RedirectResponse
    {
        abort_unless($signalement->statut === Signalement::EN_ATTENTE, 422, 'Ce signalement a déjà été traité.');

        $data = $request->validate([
            'motif_rejet' => ['nullable', 'string', 'max:1000'],
        ]);

        $signalement->forceFill([
            'statut' => Signalement::REJETE,
            'motif_rejet' => $data['motif_rejet'] ?? null,
            'traite_le' => now(),
        ])->save();

        return redirect()->route('admin.signalements.show', $signalement)
            ->with('success', 'Le signalement a été rejeté. Le message de non-éligibilité est visible par le déclarant.');
    }

    /**
     * Nombre de nouveaux signalements (pour la pastille rouge du menu).
     */
    public function nonLus(): JsonResponse
    {
        return response()->json(['count' => Signalement::nonLus()->count()]);
    }
}
