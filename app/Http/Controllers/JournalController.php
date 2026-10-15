<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\User;
use App\Services\Parrainage\FichiersExtraction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Journal d'audit (administrateurs) : qui a fait quoi, quand, depuis où, dans tous les modules.
 * Consultation filtrée et export Excel ; le journal ne se modifie ni ne se supprime.
 */
class JournalController extends Controller
{
    private const PAR_PAGE = 30;

    /** Export Excel limité : au-delà, affiner la période. */
    private const EXPORT_MAX = 20000;

    public function __construct(private readonly FichiersExtraction $fichiers)
    {
    }

    public function index(Request $request): View
    {
        $filtres = $this->lireFiltres($request);

        return view('admin.journal.index', [
            'entrees' => $this->requete($filtres)->with('utilisateur')->paginate(self::PAR_PAGE)->withQueryString(),
            'filtres' => $filtres,
            'utilisateurs' => User::withTrashed()->whereIn('id', JournalActivite::whereNotNull('user_id')->select('user_id'))->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $filtres = $this->lireFiltres($request);
        FichiersExtraction::prevoirGrosFichier();
        $requete = $this->requete($filtres);
        if (($total = (clone $requete)->count()) > self::EXPORT_MAX) {
            return back()->with('error', "{$total} entrées correspondent aux filtres : l’export est limité à " . self::EXPORT_MAX . '. Réduisez la période.');
        }

        $colonnes = [
            'Date' => fn (JournalActivite $e) => $e->created_at->format('d/m/Y H:i:s'),
            'Utilisateur' => fn (JournalActivite $e) => $e->utilisateur?->name ?? 'Système',
            'E-mail' => fn (JournalActivite $e) => $e->utilisateur?->email ?? ($e->details['email'] ?? ''),
            'Module' => fn (JournalActivite $e) => $e->libelleModule(),
            'Action' => fn (JournalActivite $e) => $e->action,
            'Description' => fn (JournalActivite $e) => $e->description,
            'Adresse IP' => fn (JournalActivite $e) => $e->details['ip'] ?? '',
            'Détails' => fn (JournalActivite $e) => self::detailsEnTexte($e->details),
        ];
        $entete = [
            'titre' => 'Journal d’audit',
            'lignes' => ["{$total} entrée(s)", 'Extrait le ' . now()->format('d/m/Y à H:i') . " par {$request->user()->name}"],
        ];

        JournalActivite::consigner('extraction.journal', null, "Export du journal d’audit ({$total} entrée(s))", ['filtres' => array_filter($filtres), 'lignes' => $total]);

        // Entrées lues par paquets au fil de l'écriture (20 000 × 8 colonnes : sous la limite de FichiersExtraction)
        return $this->fichiers->listeExcel(FichiersExtraction::dansLOrdre($requete, ['utilisateur'], 1000), $colonnes, $entete, 'journal-audit-' . now()->format('Y-m-d-His') . '.xlsx', 'Journal');
    }

    /** Détails lisibles : « motif : … ; champs : nom, prénom ». */
    public static function detailsEnTexte(?array $details): string
    {
        return collect($details ?? [])
            ->except('ip')
            ->map(fn ($valeur, $cle) => str_replace('_', ' ', (string) $cle) . ' : ' . (is_array($valeur) ? json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $valeur))
            ->implode(' ; ');
    }

    private function lireFiltres(Request $request): array
    {
        $date = fn (string $cle) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($cle)) ? (string) $request->query($cle) : null;

        return [
            'du' => $date('du'),
            'au' => $date('au'),
            'utilisateur' => $request->integer('utilisateur') ?: null,
            'module' => array_key_exists((string) $request->query('module'), JournalActivite::MODULES) ? (string) $request->query('module') : null,
            'q' => trim((string) $request->query('q')) ?: null,
        ];
    }

    private function requete(array $f): Builder
    {
        return JournalActivite::query()
            ->when($f['du'], fn ($q, $du) => $q->where('created_at', '>=', $du . ' 00:00:00'))
            ->when($f['au'], fn ($q, $au) => $q->where('created_at', '<=', $au . ' 23:59:59'))
            ->when($f['utilisateur'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($f['module'], fn ($q, $module) => $q->where(fn ($q) => $q->where('action', $module)->orWhereLike('action', $module . '.%')))
            ->when($f['q'], fn ($q, $terme) => $q->whereLike('description', "%{$terme}%"))
            ->latest('created_at')
            ->latest('id');
    }
}
