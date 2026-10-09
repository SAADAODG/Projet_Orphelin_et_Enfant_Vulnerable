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

    public function indicateurs(array $f): array
    {
        $sessions = $f['session'] ? collect([$f['session']]) : $f['sessions'];
        $base = $this->oevs($f);
        $ids = array_flip((clone $base)->pluck('id')->all());

        // État : liste définitive et liste d'attente des sessions, limitées aux OEV de la zone
        $definitive = $this->source->disponible()
            ? $sessions->flatMap(fn (SessionParrainage $s) => $this->source->listeDefinitive($s))
                ->filter(fn (array $b) => isset($ids[$b['oev_id']]) && $this->typeConvient($b['type_appui'] ?? null, $f['type_appui']))
            : null;
        $attente = $this->source->disponible()
            ? $sessions->flatMap(fn (SessionParrainage $s) => $this->source->listeAttente($s))->filter(fn (array $b) => isset($ids[$b['oev_id']]))
            : null;

        $appuis = $this->appuis($f, $base);
        $oevEtat = $definitive?->pluck('oev_id')->unique()->values() ?? collect();
        $oevPartenaires = (clone $appuis)->distinct()->pluck('oev_id');
        $appuyes = $oevEtat->merge($oevPartenaires)->unique()->values();
        $eligibles = count($ids);

        return [
            'budget' => $this->budget($sessions, $definitive, $f),
            'couverture' => [
                'eligibles' => $eligibles,
                'etat' => $definitive === null ? null : $oevEtat->count(),
                'partenaires' => $oevPartenaires->count(),
                'attente' => $attente?->pluck('oev_id')->unique()->count(),
                'appuyes' => $appuyes->count(),
                'taux' => $eligibles ? round($appuyes->count() / $eligibles * 100, 1) : null,
            ],
            'profil' => $this->profil($appuyes),
            'partenaires' => $this->partenaires($f, $appuis),
            'resultats' => $this->resultats($f['annee'], $oevEtat->merge($oevPartenaires)->unique()),
        ];
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

        return AppuiPartenaire::where('annee', $f['annee'])
            ->whereIn('oev_id', (clone $base)->select('id'))
            ->when($natures, fn ($q) => $q->whereIn('nature_appui_id', NatureAppui::whereIn('code', $natures)->select('id')));
    }

    private function budget(Collection $sessions, ?Collection $definitive, array $f): array
    {
        $enveloppe = (int) $sessions->sum('enveloppe');
        $engage = $definitive === null ? null : (int) $definitive->sum('montant_retenu');

        // Quotas par région : une session choisie dont les quotas sont actifs ; DR / DP : leur région seulement
        $quotas = null;
        if ($f['session']?->quotas_actifs) {
            $regionsEngagees = $definitive === null ? null : Oev::whereIn('id', $definitive->pluck('oev_id'))->pluck('region_id', 'id');
            $quotas = Region::orderBy('nom')
                ->when($f['perimetre']->region, fn ($q, $region) => $q->whereKey($region->id))
                ->get(['id', 'nom'])
                ->map(function (Region $region) use ($f, $definitive, $regionsEngagees) {
                    $quota = (int) ($f['session']->quotas->firstWhere('region_id', $region->id)?->montant ?? 0);
                    $engageRegion = $definitive === null ? null
                        : (int) $definitive->filter(fn ($b) => ($regionsEngagees[$b['oev_id']] ?? null) === $region->id)->sum('montant_retenu');

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

    /** Répartition des OEV appuyés (État et partenaires) par sexe, âge, cycle ou filière, vulnérabilité, handicap. */
    private function profil(Collection $ids): array
    {
        $oevs = Oev::whereIn('id', $ids)->get(['id', 'sexe', 'date_naissance', 'niveau_etude', 'formation_professionnelle', 'vulnerabilites', 'handicap']);

        $compter = fn (callable $cle, array $libelles) => collect($libelles)
            ->map(fn ($libelle, $code) => ['libelle' => $libelle, 'total' => $oevs->filter(fn (Oev $o) => $cle($o) === $code)->count()])
            ->values();

        return [
            'total' => $oevs->count(),
            'sexe' => $compter(fn (Oev $o) => $o->sexe, Oev::SEXES),
            'age' => $compter(fn (Oev $o) => match (true) {
                $o->age() < 6 => 'moins_6',
                $o->age() < 12 => '6_11',
                $o->age() < 16 => '12_15',
                $o->age() < 18 => '16_17',
                default => '18_plus',
            }, self::TRANCHES_AGE),
            'cycle' => $compter(
                fn (Oev $o) => $o->formation_professionnelle ? 'formation' : ($o->niveau_etude ?: 'non_precise'),
                Oev::NIVEAUX_ETUDE + ['formation' => 'Formation professionnelle', 'non_precise' => 'Non scolarisé ou non précisé'],
            ),
            'vulnerabilites' => collect(Oev::VULNERABILITES)
                ->map(fn ($libelle, $code) => ['libelle' => $libelle, 'total' => $oevs->filter(fn (Oev $o) => in_array($code, $o->vulnerabilites ?? [], true))->count()])
                ->filter(fn ($ligne) => $ligne['total'] > 0)
                ->sortByDesc('total')
                ->values(),
            'handicap' => $compter(fn (Oev $o) => $o->handicap ? 'oui' : 'non', ['oui' => 'Avec handicap', 'non' => 'Sans handicap']),
        ];
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

    /** Résultats de fin d'année des OEV appuyés (suivi scolaire) ; null tant que le module n'est pas branché. */
    private function resultats(string $annee, Collection $ids): ?array
    {
        if (! $this->source->disponible()) {
            return null;
        }
        $resultats = $this->source->resultatsScolaires($annee)->whereIn('oev_id', $ids->all());
        $total = $resultats->count();

        return collect(['admis' => 'Réussite', 'redouble' => 'Redoublement', 'abandon' => 'Abandon'])
            ->map(fn ($libelle, $code) => [
                'libelle' => $libelle,
                'total' => $n = $resultats->where('resultat', $code)->count(),
                'taux' => $total ? round($n / $total * 100, 1) : null,
            ])
            ->values()
            ->all();
    }
}
