<?php

namespace App\Http\Controllers;

use App\Models\Oev;
use App\Models\Plainte;
use App\Models\Region;
use App\Models\Signalement;
use App\Models\User;
use App\Support\Perimetre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Tableau de bord propre à chaque niveau : le DP suit les dossiers de sa province,
 * le DR ceux de sa région, le niveau central l'ensemble du pays (filtrable).
 */
class DashboardController extends Controller
{
    private const MOIS_AFFICHES = 6;

    private User $utilisateur;

    public function __invoke(Request $request): View
    {
        $utilisateur = $this->utilisateur = $request->user();
        $perimetre = Perimetre::pour($utilisateur, $request);
        $voitSignalements = $utilisateur->can('voir signalements');

        $etats = $this->compter($this->oevs($perimetre), 'statut_dossier');
        // Seules les étapes du circuit où l'utilisateur intervient sont affichées
        $etatsVisibles = Oev::etatsVisiblesPar($utilisateur) ?? array_keys(Oev::ETATS);

        return view('index', [
            'utilisateur' => $utilisateur,
            'perimetre' => $perimetre,
            'indicateurs' => $this->indicateurs($utilisateur->niveau(), $perimetre, $etats),
            'circuit' => collect(Oev::ETATS)->only($etatsVisibles)->map(fn ($libelle, $etat) => [
                'libelle' => $libelle,
                'total' => (int) ($etats[$etat] ?? 0),
                'couleur' => Oev::COULEURS_ETATS[$etat],
            ]),
            'oevIntegres' => $this->oevIntegres($perimetre),
            // null : module non accessible à l'utilisateur, le panneau n'est pas affiché
            'signalements' => $voitSignalements ? $this->compter($perimetre->appliquer(Signalement::query()), 'statut') : null,
            'signalementsNonLus' => $voitSignalements ? $perimetre->appliquer(Signalement::nonLus())->count() : 0,
            'plaintes' => $utilisateur->can('voir plaintes') ? $this->compter($perimetre->appliquer(Plainte::query()), 'statut') : null,
            'repartition' => $this->repartition($perimetre, $voitSignalements),
            'evolution' => $this->evolution($perimetre, $voitSignalements),
            'aTraiter' => $this->aTraiter($utilisateur, $perimetre),
            'regions' => $utilisateur->niveau() === User::NIVEAU_CENTRAL ? Region::with(['provinces' => fn ($q) => $q->orderBy('nom')])->orderBy('nom')->get() : collect(),
        ]);
    }

    /** Dossiers du périmètre affiché, limités à ceux que l'utilisateur a le droit de voir. */
    private function oevs(Perimetre $perimetre): Builder
    {
        return $perimetre->appliquer(Oev::visiblesPar($this->utilisateur));
    }

    /** Nombre d'enregistrements par valeur de $colonne, ex. ['soumis' => 3, 'valide' => 1]. */
    private function compter(Builder $query, string $colonne): Collection
    {
        return $query->selectRaw("{$colonne} as cle, count(*) as total")->groupBy($colonne)->pluck('total', 'cle')->map(fn ($n) => (int) $n);
    }

    /**
     * Cartes « à faire » du niveau : ce qui attend une action de l'utilisateur, avec un lien
     * vers l'écran où la traiter (uniquement s'il y a accès).
     */
    private function indicateurs(string $niveau, Perimetre $perimetre, Collection $etats): array
    {
        $n = fn (string ...$liste) => collect($liste)->sum(fn ($etat) => $etats[$etat] ?? 0);

        return match ($niveau) {
            User::NIVEAU_PROVINCE => [
                ['libelle' => 'En constitution', 'valeur' => $n(Oev::ETAT_BROUILLON), 'icone' => 'bi-folder2-open', 'couleur' => 'primary',
                    'aide' => 'dossiers à compléter', 'route' => ['oevs.index', ['etat' => Oev::ETAT_BROUILLON]], 'permission' => 'constituer dossiers'],
                ['libelle' => 'Prêts à soumettre', 'valeur' => $this->oevs($perimetre)->etat(Oev::ETAT_BROUILLON)->dossierComplet()->count(), 'icone' => 'bi-send-check', 'couleur' => 'success',
                    'aide' => 'complets, à soumettre au DR', 'route' => ['oevs.index', ['etat' => Oev::ETAT_BROUILLON]], 'permission' => 'constituer dossiers'],
                ['libelle' => 'Non conformes', 'valeur' => $n(Oev::ETAT_NON_CONFORME), 'icone' => 'bi-exclamation-octagon', 'couleur' => 'danger',
                    'aide' => 'renvoyés par le DR, à corriger', 'route' => ['oevs.index', ['etat' => Oev::ETAT_NON_CONFORME]], 'permission' => 'constituer dossiers'],
                ['libelle' => 'Chez le DR', 'valeur' => $n(Oev::ETAT_SOUMIS, Oev::ETAT_COMPLEMENT), 'icone' => 'bi-hourglass-split', 'couleur' => 'warning',
                    'aide' => 'en cours de vérification', 'route' => ['oevs.index', ['etat' => Oev::ETAT_SOUMIS]], 'permission' => 'constituer dossiers'],
            ],
            User::NIVEAU_REGION => [
                ['libelle' => 'À vérifier', 'valeur' => $n(Oev::ETAT_SOUMIS), 'icone' => 'bi-clipboard-check', 'couleur' => 'warning',
                    'aide' => 'soumis par les DP', 'route' => ['oevs.validation', ['etat' => Oev::ETAT_SOUMIS]], 'permission' => 'valider dossiers'],
                ['libelle' => 'Compléments demandés', 'valeur' => $n(Oev::ETAT_COMPLEMENT), 'icone' => 'bi-arrow-return-left', 'couleur' => 'danger',
                    'aide' => 'renvoyés par le niveau central', 'route' => ['oevs.validation', ['etat' => Oev::ETAT_COMPLEMENT]], 'permission' => 'valider dossiers'],
                ['libelle' => 'Validés', 'valeur' => $n(Oev::ETAT_VALIDE), 'icone' => 'bi-patch-check', 'couleur' => 'primary',
                    'aide' => 'transmis au niveau central', 'route' => ['oevs.validation', ['etat' => Oev::ETAT_VALIDE]], 'permission' => 'valider dossiers'],
                ['libelle' => 'Chez les DP', 'valeur' => $perimetre->appliquer(Oev::etat(Oev::ETAT_BROUILLON, Oev::ETAT_NON_CONFORME))->count(), 'icone' => 'bi-folder2-open', 'couleur' => 'success',
                    'aide' => 'en constitution ou en correction', 'route' => null, 'permission' => null],
            ],
            default => [
                ['libelle' => 'À intégrer', 'valeur' => $n(Oev::ETAT_VALIDE), 'icone' => 'bi-person-check', 'couleur' => 'warning',
                    'aide' => 'dossiers validés par les DR', 'route' => ['oevs.integration', ['etat' => Oev::ETAT_VALIDE]], 'permission' => 'intégrer OEV'],
                ['libelle' => 'Compléments en attente', 'valeur' => $n(Oev::ETAT_COMPLEMENT), 'icone' => 'bi-arrow-return-left', 'couleur' => 'primary',
                    'aide' => 'renvoyés aux DR', 'route' => ['oevs.integration', ['etat' => Oev::ETAT_COMPLEMENT]], 'permission' => 'intégrer OEV'],
                ['libelle' => 'OEV intégrés', 'valeur' => $n(Oev::ETAT_INTEGRE), 'icone' => 'bi-people', 'couleur' => 'success',
                    'aide' => 'enfants pris en charge', 'route' => ['oevs.liste', []], 'permission' => 'voir OEV'],
                ['libelle' => 'Nouvelles plaintes', 'valeur' => $perimetre->appliquer(Plainte::where('statut', Plainte::NOUVELLE))->count(), 'icone' => 'bi-chat-text', 'couleur' => 'danger',
                    'aide' => 'plaintes et avis à examiner', 'route' => ['admin.plaintes.index', ['statut' => Plainte::NOUVELLE]], 'permission' => 'voir plaintes'],
            ],
        };
    }

    /** OEV intégrés : total et répartition par sexe et par statut. */
    private function oevIntegres(Perimetre $perimetre): array
    {
        $integres = fn () => $this->oevs($perimetre)->etat(Oev::ETAT_INTEGRE);
        $parStatut = $this->compter($integres(), 'statut');
        $parSexe = $this->compter($integres(), 'sexe');

        return [
            'total' => $parStatut->sum(),
            'handicap' => $integres()->where('handicap', true)->count(),
            'parSexe' => collect(Oev::SEXES)->map(fn ($libelle, $cle) => ['libelle' => $libelle, 'total' => $parSexe[$cle] ?? 0]),
            'parStatut' => collect(Oev::STATUTS)->map(fn ($libelle, $cle) => ['libelle' => $libelle, 'total' => $parStatut[$cle] ?? 0]),
        ];
    }

    /** Chiffres par localité du niveau inférieur (régions, provinces ou communes), les plus actives en premier. */
    private function repartition(Perimetre $perimetre, bool $avecSignalements): Collection
    {
        $colonne = $perimetre->colonneDetail();
        $enCours = $this->compter($this->oevs($perimetre)->where('statut_dossier', '!=', Oev::ETAT_INTEGRE), $colonne);
        $integres = $this->compter($this->oevs($perimetre)->etat(Oev::ETAT_INTEGRE), $colonne);
        $signalements = $avecSignalements ? $this->compter($perimetre->appliquer(Signalement::query()), $colonne) : collect();

        return $perimetre->localitesDetail()
            ->map(fn ($nom, $id) => [
                'nom' => $nom,
                'enCours' => $enCours[$id] ?? 0,
                'integres' => $integres[$id] ?? 0,
                'signalements' => $signalements[$id] ?? 0,
            ])
            ->sortByDesc(fn ($ligne) => $ligne['integres'] + $ligne['enCours'] + $ligne['signalements'])
            ->values();
    }

    /** Signalements reçus et OEV intégrés sur les derniers mois (mois en cours inclus). */
    private function evolution(Perimetre $perimetre, bool $avecSignalements): Collection
    {
        return collect(range(self::MOIS_AFFICHES - 1, 0))->map(function (int $decalage) use ($perimetre, $avecSignalements) {
            $debut = Carbon::now()->locale('fr')->startOfMonth()->subMonthsNoOverflow($decalage);
            $periode = [$debut, $debut->copy()->endOfMonth()];

            return [
                'mois' => ucfirst($debut->translatedFormat('M')),
                'moisComplet' => ucfirst($debut->translatedFormat('F Y')),
                'signalements' => $avecSignalements ? $perimetre->appliquer(Signalement::whereBetween('created_at', $periode))->count() : 0,
                'integres' => $this->oevs($perimetre)->whereBetween('integre_at', $periode)->count(),
            ];
        });
    }

    /** Dossiers qui attendent une action de l'utilisateur : l'état prioritaire d'abord, puis les plus anciens. */
    private function aTraiter(User $utilisateur, Perimetre $perimetre): Collection
    {
        [$etats, $date] = match ($utilisateur->niveau()) {
            User::NIVEAU_PROVINCE => [[Oev::ETAT_NON_CONFORME, Oev::ETAT_BROUILLON], 'updated_at'],
            User::NIVEAU_REGION => [[Oev::ETAT_COMPLEMENT, Oev::ETAT_SOUMIS], 'soumis_at'],
            default => [[Oev::ETAT_VALIDE], 'verifie_at'],
        };

        return $this->oevs($perimetre)
            ->etat(...$etats)
            ->with(['province', 'commune'])
            ->withCount('documents')
            ->orderByRaw('case when statut_dossier = ? then 0 else 1 end', [$etats[0]])
            ->orderBy($date)
            ->limit(5)
            ->get();
    }
}
