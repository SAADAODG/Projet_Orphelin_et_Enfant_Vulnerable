<?php

namespace App\Services\Parrainage;

use App\Http\Controllers\SessionParrainageController;
use App\Models\AppuiPartenaire;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Support\Perimetre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du tableau de bord « Pilotage » : budget, couverture, profil des OEV appuyés,
 * partenaires et résultats scolaires, dans la zone de l'utilisateur.
 *
 * Les chiffres de l'État (liste définitive, liste d'attente, résultats) viennent de SourceSelection :
 * tant que les modules de Nakoulma ne sont pas branchés, ils valent null (« données non disponibles »).
 */
class PilotageParrainage
{
    public const TRANCHES_AGE = [
        'moins_6' => 'Moins de 6 ans',
        '6_11' => '6 à 11 ans',
        '12_15' => '12 à 15 ans',
        '16_17' => '16 à 17 ans',
        '18_plus' => '18 ans et plus',
    ];

    public function __construct(private readonly SourceSelection $source)
    {
    }

    /**
     * Filtres communs à tous les écrans : année, session, type d'appui, région / province (niveau
     * central ; le DR et le DP sont limités à leur zone), sexe.
     *
     * @return array{annee: string, session: SessionParrainage|null, sessions: Collection, type_appui: string, sexe: string, perimetre: Perimetre}
     */
    public function filtres(Request $request, User $utilisateur): array
    {
        $annee = (string) $request->query('annee', '');
        if (! preg_match('/^\d{4}-\d{4}$/', $annee)) {
            $annee = SessionParrainage::orderByDesc('annee')->value('annee') ?? SessionParrainageController::anneeScolaireCourante();
        }
        $sessions = SessionParrainage::with('quotas')->where('annee', $annee)->orderBy('numero')->get();

        return [
            'annee' => $annee,
            'session' => $sessions->firstWhere('id', $request->integer('session_id')),
            'sessions' => $sessions,
            'type_appui' => array_key_exists((string) $request->query('type_appui'), SessionParrainage::TYPES_APPUI) ? (string) $request->query('type_appui') : '',
            'sexe' => array_key_exists((string) $request->query('sexe'), Oev::SEXES) ? (string) $request->query('sexe') : '',
            'perimetre' => Perimetre::pour($utilisateur, $request),
        ];
    }

    /**
     * Les OEV de la zone ne sont jamais chargés : comptages et profil sont calculés par la base.
     * Listes de l'État : lues comme requêtes quand elles sont dans la base (SourceSelectionEnBase), sinon
     * (source externe) en mémoire, leurs identifiants confrontés à la zone par paquets (voir dansLaZone()).
     */
    public function indicateurs(array $f): array
    {
        $sessions = $f['session'] ? collect([$f['session']]) : $f['sessions'];
        $base = $this->oevs($f);
        $appuis = $this->appuis($f, $base);
        $partenaires = (clone $appuis)->distinct()->count('oev_id');
        $eligibles = (clone $base)->count();

        $etat = match (true) {
            ! $this->source->disponible() => null,
            $this->source instanceof SourceSelectionEnBase => $this->etatEnBase($this->source, $sessions, $f, $base, $appuis),
            default => $this->etatEnMemoire($sessions, $f, $base, $appuis),
        };
        $appuyes = $partenaires + ($etat['nombre_seul'] ?? 0);

        return [
            'budget' => $this->budget($sessions, $etat, $f),
            'couverture' => [
                'eligibles' => $eligibles,
                'etat' => $etat['nombre'] ?? null,
                'partenaires' => $partenaires,
                'attente' => $etat['attente'] ?? null,
                'appuyes' => $appuyes,
                'taux' => $eligibles ? round($appuyes / $eligibles * 100, 1) : null,
            ],
            'profil' => $this->profil($appuis, $etat['profil_seul'] ?? []),
            'partenaires' => $this->partenaires($f, $appuis),
            'resultats' => $etat['resultats'] ?? null,
        ];
    }

    /**
     * Chiffres de l'État calculés par la base (listes de la sélection dans la base).
     *
     * @return array{engage: int, nombre: int, nombre_seul: int, attente: int, engage_par_region: Collection, profil_seul: array<int, Builder>, resultats: array}
     */
    private function etatEnBase(SourceSelectionEnBase $source, Collection $sessions, array $f, Builder $base, Builder $appuis): array
    {
        $types = self::naturesDuType($f['type_appui']);
        $definitive = fn () => DB::query()->fromSub($source->requeteListeDefinitive($sessions), 'd')
            ->whereExists($this->dansLaZoneSql($base, 'd.oev_id'))
            ->when($types, fn ($q) => $q->where(fn ($q) => $q->whereNull('d.type_appui')->orWhereIn('d.type_appui', $types)));
        $idsEtat = fn () => $definitive()->select('d.oev_id');
        $totaux = $definitive()->selectRaw('COALESCE(SUM(d.montant_retenu), 0) AS engage, COUNT(DISTINCT d.oev_id) AS nombre')->first();
        $seul = fn () => Oev::whereIn('id', $idsEtat())
            ->whereNotExists((clone $appuis)->select(DB::raw(1))->whereColumn('appuis_partenaires.oev_id', 'oevs.id'));
        $parRegion = $definitive()->join('oevs', 'oevs.id', '=', 'd.oev_id')
            ->selectRaw('oevs.region_id, SUM(d.montant_retenu) AS total')->groupBy('oevs.region_id')
            ->pluck('total', 'region_id')->map(fn ($n) => (int) $n);
        $resultats = DB::query()->fromSub($source->requeteResultatsScolaires($f['annee']), 'r')
            ->where(fn ($q) => $q->whereIn('r.oev_id', $idsEtat())->orWhereIn('r.oev_id', (clone $appuis)->select('oev_id')))
            ->selectRaw('r.resultat, COUNT(*) AS total')->groupBy('r.resultat')
            ->pluck('total', 'resultat')->map(fn ($n) => (int) $n);

        return [
            'engage' => (int) $totaux->engage,
            'nombre' => (int) $totaux->nombre,
            'nombre_seul' => $seul()->count(),
            'attente' => DB::query()->fromSub($source->requeteListeAttente($sessions), 'a')
                ->whereExists($this->dansLaZoneSql($base, 'a.oev_id'))->distinct()->count('a.oev_id'),
            'engage_par_region' => $parRegion,
            'profil_seul' => [$seul()],
            'resultats' => $this->tauxResultats($resultats),
        ];
    }

    /** Mêmes chiffres, à partir des listes en mémoire (source externe à la base). */
    private function etatEnMemoire(Collection $sessions, array $f, Builder $base, Builder $appuis): array
    {
        $definitive = $sessions->flatMap(fn (SessionParrainage $s) => $this->source->listeDefinitive($s))
            ->filter(fn (array $b) => $this->typeConvient($b['type_appui'] ?? null, $f['type_appui']));
        $regions = $this->dansLaZone($base, $definitive->pluck('oev_id'));
        $definitive = $definitive->filter(fn (array $b) => $regions->has($b['oev_id']));
        $attente = $sessions->flatMap(fn (SessionParrainage $s) => $this->source->listeAttente($s));
        $attenteZone = $this->dansLaZone($base, $attente->pluck('oev_id'));

        $oevEtat = $definitive->pluck('oev_id')->unique()->values();
        // OEV appuyés par l'État sans l'être aussi par un partenaire : disjoints des appuyés par un partenaire
        $etatSeul = $oevEtat->diff($this->parPaquets($oevEtat, fn ($ids) => (clone $appuis)->whereIn('oev_id', $ids)->distinct()->pluck('oev_id')))->values();

        // Résultats de l'année des OEV appuyés : par l'État, ou par un partenaire
        $resultats = $this->source->resultatsScolaires($f['annee']);
        $parEtat = $oevEtat->flip();
        $parPartenaire = $this->parPaquets(
            $resultats->pluck('oev_id')->reject(fn ($id) => $parEtat->has($id)),
            fn ($ids) => (clone $appuis)->whereIn('oev_id', $ids)->distinct()->pluck('oev_id'),
        )->flip();
        $resultats = $resultats->filter(fn (array $r) => $parEtat->has($r['oev_id']) || $parPartenaire->has($r['oev_id']))
            ->countBy('resultat');

        return [
            'engage' => (int) $definitive->sum('montant_retenu'),
            'nombre' => $oevEtat->count(),
            'nombre_seul' => $etatSeul->count(),
            'attente' => $attente->filter(fn (array $b) => $attenteZone->has($b['oev_id']))->pluck('oev_id')->unique()->count(),
            'engage_par_region' => $definitive->groupBy(fn ($b) => $regions[$b['oev_id']])->map(fn ($l) => (int) $l->sum('montant_retenu')),
            'profil_seul' => $etatSeul->chunk(self::PAQUET)->map(fn (Collection $paquet) => Oev::whereIn('id', $paquet->values()->all()))->all(),
            'resultats' => $this->tauxResultats($resultats),
        ];
    }

    /** Réussite, redoublement, abandon : nombre et taux, à partir des nombres par résultat. */
    private function tauxResultats(Collection $parResultat): array
    {
        $total = $parResultat->sum();

        return collect(['admis' => 'Réussite', 'redouble' => 'Redoublement', 'abandon' => 'Abandon'])
            ->map(fn ($libelle, $code) => [
                'libelle' => $libelle,
                'total' => $n = (int) ($parResultat[$code] ?? 0),
                'taux' => $total ? round($n / $total * 100, 1) : null,
            ])
            ->values()
            ->all();
    }

    /** « L'OEV $colonne est dans la zone » : sous-requête EXISTS corrélée, vérifiée par la clé de l'OEV. */
    private function dansLaZoneSql(Builder $base, string $colonne): Builder
    {
        return (clone $base)->select(DB::raw(1))->whereColumn('oevs.id', $colonne);
    }

    /** Taille des paquets d'identifiants envoyés à la base (bien en dessous de la limite de paramètres de PostgreSQL). */
    private const PAQUET = 5000;

    /** Applique $requete à $ids par paquets et réunit les résultats. */
    private function parPaquets(Collection $ids, callable $requete): Collection
    {
        return $ids->unique()->chunk(self::PAQUET)->flatMap(fn (Collection $paquet) => $requete($paquet->values()->all()))->values();
    }

    /**
     * Parmi $ids, les OEV de la zone (requête $base) : oev_id => region_id.
     *
     * @return Collection<int, int|null>
     */
    private function dansLaZone(Builder $base, Collection $ids): Collection
    {
        $zone = collect();
        foreach ($ids->unique()->chunk(self::PAQUET) as $paquet) {
            foreach ((clone $base)->whereIn('id', $paquet->values()->all())->pluck('region_id', 'id') as $id => $region) {
                $zone[(int) $id] = $region;
            }
        }

        return $zone;
    }

    /** OEV bénéficiaires (intégrés, non désactivés) de la zone, filtrés par sexe. */
    public function oevs(array $f): Builder
    {
        return $f['perimetre']->appliquer(Oev::beneficiaires())
            ->when($f['sexe'] !== '', fn ($q) => $q->where('sexe', $f['sexe']));
    }

    /** Natures d'appui correspondant au type choisi : scolaire, formation professionnelle, ou les deux. */
    public static function naturesDuType(string $type): array
    {
        return match ($type) {
            SessionParrainage::TYPE_SCOLAIRE => [SessionParrainage::TYPE_SCOLAIRE],
            SessionParrainage::TYPE_FORMATION => [SessionParrainage::TYPE_FORMATION],
            SessionParrainage::TYPE_LES_DEUX => [SessionParrainage::TYPE_SCOLAIRE, SessionParrainage::TYPE_FORMATION],
            default => [],
        };
    }

    private function typeConvient(?string $typeBeneficiaire, string $filtre): bool
    {
        return $filtre === '' || $typeBeneficiaire === null || in_array($typeBeneficiaire, self::naturesDuType($filtre), true);
    }

    /** Appuis des partenaires de l'année pour les OEV de la zone (et du type d'appui choisi). */
    private function appuis(array $f, Builder $base): Builder
    {
        $natures = self::naturesDuType($f['type_appui']);

        // Zone : « EXISTS » corrélé (l'OEV de chaque appui est vérifié par sa clé), pas « IN (tous les OEV de la zone) »
        return AppuiPartenaire::where('annee', $f['annee'])
            ->whereExists($this->dansLaZoneSql($base, 'appuis_partenaires.oev_id'))
            ->when($natures, fn ($q) => $q->whereIn('nature_appui_id', NatureAppui::whereIn('code', $natures)->select('id')));
    }

    private function budget(Collection $sessions, ?array $etat, array $f): array
    {
        $enveloppe = (int) $sessions->sum('enveloppe');
        $engage = $etat['engage'] ?? null;

        // Quotas par région : une session choisie dont les quotas sont actifs ; DR / DP : leur région seulement
        $quotas = null;
        if ($f['session']?->quotas_actifs) {
            $quotas = Region::orderBy('nom')
                ->when($f['perimetre']->region, fn ($q, $region) => $q->whereKey($region->id))
                ->get(['id', 'nom'])
                ->map(function (Region $region) use ($f, $etat) {
                    $quota = (int) ($f['session']->quotas->firstWhere('region_id', $region->id)?->montant ?? 0);
                    $engageRegion = $etat === null ? null : (int) ($etat['engage_par_region'][$region->id] ?? 0);

                    return ['region' => $region->nom, 'quota' => $quota, 'engage' => $engageRegion, 'reste' => $engageRegion === null ? null : $quota - $engageRegion];
                });
        }

        return [
            'sessions' => $sessions->count(),
            'enveloppe' => $enveloppe,
            'engage' => $engage,
            'reste' => $engage === null ? null : $enveloppe - $engage,
            'taux' => $engage === null || ! $enveloppe ? null : round($engage / $enveloppe * 100, 1),
            'quotas' => $quotas,
        ];
    }

    /**
     * Répartition des OEV appuyés (État et partenaires) par sexe, âge, cycle ou filière, vulnérabilité, handicap.
     * Calculée par la base, une requête par ensemble : les appuyés par un partenaire, puis les appuyés par
     * l'État seul (ensembles disjoints : les comptages s'additionnent).
     */
    /** @param  array<int, Builder>  $etatSeul  requêtes des OEV appuyés par l'État seul (ensembles disjoints) */
    private function profil(Builder $appuis, array $etatSeul): array
    {
        $totaux = $this->compterProfil(Oev::whereIn('id', (clone $appuis)->select('oev_id')));
        foreach ($etatSeul as $requete) {
            foreach ($this->compterProfil($requete) as $cle => $n) {
                $totaux[$cle] = ($totaux[$cle] ?? 0) + $n;
            }
        }
        $total = fn (string $cle) => (int) ($totaux[$cle] ?? 0);
        $repartition = fn (string $prefixe, array $libelles) => collect($libelles)
            ->map(fn ($libelle, $code) => ['libelle' => $libelle, 'total' => $total("{$prefixe}_{$code}")])
            ->values();

        return [
            'total' => $total('total'),
            'sexe' => $repartition('sexe', Oev::SEXES),
            'age' => $repartition('age', self::TRANCHES_AGE),
            'cycle' => $repartition('cycle', Oev::NIVEAUX_ETUDE + ['formation' => 'Formation professionnelle', 'non_precise' => 'Non scolarisé ou non précisé']),
            'vulnerabilites' => collect(Oev::VULNERABILITES)
                ->map(fn ($libelle, $code) => ['libelle' => $libelle, 'total' => $total("vulnerabilite_{$code}")])
                ->filter(fn ($ligne) => $ligne['total'] > 0)
                ->sortByDesc('total')
                ->values(),
            'handicap' => $repartition('handicap', ['oui' => 'Avec handicap', 'non' => 'Sans handicap']),
        ];
    }

    /**
     * Comptages du profil en une seule requête (sum(case …)) : clé => nombre.
     *
     * Âge : même règle que Oev::age() (années révolues ; sans date de naissance, 0 an). Un enfant a moins
     * de N ans s'il est né au plus tôt le lendemain de la même date, N ans plus tôt. Comparaison « >= » / « < »
     * avec une date sans heure : juste en PostgreSQL (colonne date) comme en SQLite (texte « Y-m-d H:i:s »).
     */
    private function compterProfil(Builder $oevs): array
    {
        $moinsDe = fn (int $ans) => now()->startOfDay()->subYears($ans)->addDay()->toDateString();
        $colonnes = ['count(*) as total'];
        $liaisons = [];
        $ajouter = function (string $cle, string $condition, array $valeurs = []) use (&$colonnes, &$liaisons) {
            $colonnes[] = "sum(case when {$condition} then 1 else 0 end) as {$cle}";
            array_push($liaisons, ...$valeurs);
        };

        foreach (array_keys(Oev::SEXES) as $code) {
            $ajouter("sexe_{$code}", 'sexe = ?', [$code]);
        }
        $ajouter('age_moins_6', '(date_naissance is null or date_naissance >= ?)', [$moinsDe(6)]);
        foreach (['6_11' => [12, 6], '12_15' => [16, 12], '16_17' => [18, 16]] as $tranche => [$max, $min]) {
            $ajouter("age_{$tranche}", 'date_naissance >= ? and date_naissance < ?', [$moinsDe($max), $moinsDe($min)]);
        }
        $ajouter('age_18_plus', 'date_naissance < ?', [$moinsDe(18)]);
        // Cycle : la formation professionnelle prime ; sinon le niveau d'étude, à défaut « non précisé »
        $horsFormation = '(formation_professionnelle is null or formation_professionnelle = ?)';
        foreach (array_keys(Oev::NIVEAUX_ETUDE) as $code) {
            $ajouter("cycle_{$code}", "{$horsFormation} and niveau_etude = ?", [false, $code]);
        }
        $ajouter('cycle_formation', 'formation_professionnelle = ?', [true]);
        $ajouter('cycle_non_precise', "{$horsFormation} and (niveau_etude is null or niveau_etude = '')", [false]);
        // Vulnérabilités : liste JSON lue comme texte (PostgreSQL et SQLite)
        foreach (array_keys(Oev::VULNERABILITES) as $code) {
            $ajouter("vulnerabilite_{$code}", 'CAST(vulnerabilites AS TEXT) LIKE ?', ['%"' . $code . '"%']);
        }
        $ajouter('handicap_oui', 'handicap = ?', [true]);
        $ajouter('handicap_non', '(handicap is null or handicap = ?)', [false]);

        return (array) $oevs->toBase()->selectRaw(implode(', ', $colonnes), $liaisons)->first();
    }

    private function partenaires(array $f, Builder $appuis): array
    {
        $region = $f['perimetre']->region;
        $parAgregat = fn (string $colonne) => (clone $appuis)
            ->selectRaw("{$colonne} as cle, count(distinct oev_id) as oev, coalesce(sum(montant), 0) as montant")
            ->groupBy($colonne)
            ->orderByDesc('oev')
            ->get();

        $parParrain = $parAgregat('parrain_id');
        $noms = Parrain::whereIn('id', $parParrain->pluck('cle'))->pluck('nom', 'id');
        $parNature = $parAgregat('nature_appui_id');
        $natures = NatureAppui::whereIn('id', $parNature->pluck('cle'))->pluck('libelle', 'id');

        return [
            'actifs' => Parrain::actifs()->when($region, fn ($q) => $q->intervenantDans($region))->count(),
            'montant' => (int) (clone $appuis)->sum('montant'),
            'parParrain' => $parParrain->take(10)->map(fn ($l) => ['libelle' => $noms[$l->cle] ?? '—', 'oev' => (int) $l->oev, 'montant' => (int) $l->montant]),
            'parNature' => $parNature->map(fn ($l) => ['libelle' => $natures[$l->cle] ?? '—', 'oev' => (int) $l->oev, 'montant' => (int) $l->montant]),
        ];
    }
}
