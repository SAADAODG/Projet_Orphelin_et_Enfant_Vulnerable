<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Signalements du public : le DP traite ceux de sa province, le DR consulte ceux de sa région.
 */
class SignalementController extends Controller implements HasMiddleware
{
    /** Un signalement hors de la zone de l'utilisateur n'est pas accessible. */
    public static function middleware(): array
    {
        return [
            function (Request $request, Closure $next) {
                $signalement = $request->route('signalement');
                abort_if($signalement instanceof Signalement && ! $signalement->estDansLePerimetreDe($request->user()), 403);

                return $next($request);
            },
        ];
    }

    /**
     * Liste des signalements, filtrable par statut.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'statut' => ['nullable', Rule::in(array_keys(Signalement::STATUTS))],
            'decision' => ['nullable', Rule::in(array_keys(Signalement::DECISIONS))],
        ]);

        $statut = $request->query('statut');
        $decision = $statut === Signalement::CLOTURE ? $request->query('decision') : null;

        $signalements = Signalement::with(['province', 'commune'])
            ->dansLePerimetreDe($request->user())
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->when($decision, fn ($query) => $query->where('decision', $decision))
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->query('q').'%';
                $query->where(fn ($sub) => $sub
                    ->where('recepisse', 'ilike', $q)
                    ->orWhere('enfant_nom', 'ilike', $q)
                    ->orWhere('enfant_prenom', 'ilike', $q)
                    ->orWhere('declarant_nom', 'ilike', $q)
                    ->ouLocaliteContient($q, 'ilike'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $compteurs = Signalement::dansLePerimetreDe($request->user())
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $decisions = Signalement::dansLePerimetreDe($request->user())
            ->where('statut', Signalement::CLOTURE)
            ->selectRaw('decision, count(*) as total')
            ->groupBy('decision')
            ->pluck('total', 'decision');

        return view('admin.signalements.index', [
            'signalements' => $signalements,
            'statut' => $statut,
            'decision' => $decision,
            'decisions' => $decisions,
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

        $signalement->load(['agentTraitement', 'agentCloture', 'region', 'province', 'commune']);

        return view('admin.signalements.show', ['signalement' => $signalement]);
    }

    public function valider(Request $request, Signalement $signalement): RedirectResponse
    {
        abort_unless($signalement->statut === Signalement::EN_ATTENTE, 422, 'Ce signalement a déjà été traité.');

        $signalement->forceFill([
            'statut' => Signalement::VALIDE,
            'motif_rejet' => null,
            'traite_le' => now(),
            'traite_par' => $request->user()?->id,
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
            'traite_par' => $request->user()?->id,
        ])->save();

        return redirect()->route('admin.signalements.show', $signalement)
            ->with('success', 'Le signalement a été rejeté. Le message de non-éligibilité est visible par le déclarant.');
    }

    /**
     * Clôture : le contact avec l'enfant a eu lieu et une décision de prise en charge est prise.
     */
    public function cloturer(Request $request, Signalement $signalement): RedirectResponse
    {
        abort_unless($signalement->statut === Signalement::VALIDE, 422, 'Seul un signalement validé peut être clôturé.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(array_keys(Signalement::DECISIONS))],
            'date_visite' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$signalement->created_at->toDateString()],
            'compte_rendu' => ['nullable', 'string', 'max:3000'],
            'message_declarant' => ['nullable', 'string', 'max:1000'],
        ], [
            'decision.required' => 'Choisissez la décision.',
            'date_visite.required' => 'Indiquez la date du contact avec l\'enfant.',
            'date_visite.before_or_equal' => 'La date ne peut pas être dans le futur.',
            'date_visite.after_or_equal' => 'La date ne peut pas précéder le signalement.',
        ]);

        $signalement->forceFill($data + [
            'statut' => Signalement::CLOTURE,
            'cloture_le' => now(),
            'cloture_par' => $request->user()?->id,
        ])->save();

        return redirect()->route('admin.signalements.show', $signalement)
            ->with('success', 'Signalement clôturé : '.$signalement->decision_libelle.'. Le déclarant verra la décision dans le suivi.');
    }

    /**
     * Nombre de nouveaux signalements (pour la pastille rouge du menu).
     */
    public function nonLus(Request $request): JsonResponse
    {
        return response()->json(['count' => Signalement::nonLus()->dansLePerimetreDe($request->user())->count()]);
    }
}
