<?php

namespace App\Services\Parrainage;

use App\Models\AppuiPartenaire;
use App\Models\BeneficiaireSession;
use App\Models\Ecole;
use App\Models\JournalActivite;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Province;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\SuiviParrainage;
use App\Models\User;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Module « Sélection » du parrainage : OEV éligibles à une session, classés du besoin le plus urgent au
 * moins urgent (score calculé, non affiché), ajout à la liste de la session (contrôle de l'enveloppe et
 * des quotas, RG-04), estimation du coût, validation de la liste définitive. Pas de liste d'attente : un ajout
 * qui dépasserait l'enveloppe (ou un quota) est refusé en entier.
 *
 * Appui scolaire : enfants scolarisés (frais de scolarité de la fiche) et enfants d'âge scolaire non
 * scolarisés, à inscrire (montant estimatif de la session). Formation : OEV en formation professionnelle
 * en cours (montant estimatif de la session).
 *
 * Fournit aussi aux modules Sessions, Parrains et Pilotage les données prévues par SourceSelection.
 * Les établissements (RIB) ne sont pas encore gérés : liste vide. Les résultats scolaires viennent du
 * module « Suivi des parrainés » (SuiviParrainage).
 */
class SelectionParrainage implements SourceSelectionEnBase
{
    /** Âge scolaire (années révolues) : un enfant non scolarisé de cet âge peut être inscrit grâce à l'appui scolaire. */
    public const AGE_SCOLAIRE_MIN = 6;
    public const AGE_SCOLAIRE_MAX = 16;

    /** Situation de l'OEV au regard de l'appui (filtre « scolarite » et estimation du coût). */
    public const SCOLARISE = 'scolarise';
    public const A_SCOLARISER = 'a_scolariser';
    public const EN_FORMATION = 'formation';

    public const SITUATIONS = [
        self::SCOLARISE => 'Scolarisés',
        self::A_SCOLARISER => 'Non scolarisés d’âge scolaire (à inscrire)',
        self::EN_FORMATION => 'En formation professionnelle',
    ];

    /* ---------------------------------------------------------------- Éligibles */

    /**
     * OEV éligibles à la session : bénéficiaires (intégrés, non désactivés), concernés par le type d'appui
     * (scolarisés / en formation), pas déjà choisis pour cette session ni retenus par une autre session de
     * l'année pour la même nature, ni appuyés par un partenaire si la session les exclut (RG-07).
     * Chaque OEV porte une colonne « score » (urgence du besoin), et la requête est triée par score décroissant.
     */
    public function eligibles(SessionParrainage $session, array $f = []): Builder
    {
        return $this->recherche($session, $f)->whereIn('oevs.id', $this->baseEligibles($session)->select('oevs.id'));
    }

    /**
     * Tous les bénéficiaires pas encore choisis pour la session, éligibles ou non (voir motifsInegibilite()),
     * réduits par les filtres ; le filtre « eligibilite » (oui / non) les sépare.
     */
    public function candidats(SessionParrainage $session, array $f = []): Builder
    {
        $requete = $this->recherche($session, $f);

        return match ($f['eligibilite'] ?? null) {
            'oui' => $requete->whereIn('oevs.id', $this->baseEligibles($session)->select('oevs.id')),
            'non' => $requete->whereNotIn('oevs.id', $this->baseEligibles($session)->select('oevs.id')),
            default => $requete,
        };
    }

    /**
     * Motif d'inéligibilité de chaque OEV non éligible parmi ceux donnés (les éligibles sont absents du tableau).
     *
     * @param  iterable<Oev>  $oevs
     * @return array<int, string>
     */
    public function motifsInegibilite(SessionParrainage $session, iterable $oevs): array
    {
        $ids = collect($oevs)->pluck('id')->all();
        if (! $ids) {
            return [];
        }

        $natures = $session->naturesCouvertes();
        $eligibles = $this->baseEligibles($session)->whereIn('oevs.id', $ids)->pluck('oevs.id')->flip();
        $autresSessions = BeneficiaireSession::retenus()
            ->whereIn('oev_id', $ids)
            ->whereIn('type_appui', $natures)
            ->whereHas('session', fn ($q) => $q->where('annee', $session->annee)->whereKeyNot($session->id))
            ->with('session')
            ->get()
            ->keyBy('oev_id');
        $appuyes = $session->exclure_deja_appuyes
            ? AppuiPartenaire::where('annee', $session->annee)->whereIn('oev_id', $ids)
                ->whereIn('nature_appui_id', NatureAppui::whereIn('code', $natures)->select('id'))->pluck('oev_id')->flip()
            : collect();
        $ages = self::AGE_SCOLAIRE_MIN . ' à ' . self::AGE_SCOLAIRE_MAX . ' ans';
        $nonConcerne = fn (Oev $oev) => match (true) {
            ! in_array(SessionParrainage::TYPE_SCOLAIRE, $natures, true) => 'Pas en formation professionnelle en cours',
            self::situationDe($oev) === self::EN_FORMATION => 'En formation professionnelle (session scolaire)',
            ! in_array(SessionParrainage::TYPE_FORMATION, $natures, true) => "Non scolarisé et hors de l’âge scolaire ({$ages})",
            default => "Ni scolarisé, ni d’âge scolaire ({$ages}), ni en formation",
        };
        $oevs = collect($oevs)->keyBy('id');

        $motifs = [];
        foreach ($ids as $id) {
            if ($eligibles->has($id)) {
                continue;
            }
            $motifs[$id] = match (true) {
                $autresSessions->has($id) => 'Déjà retenu par la session ' . $autresSessions[$id]->session->reference(),
                $appuyes->has($id) => 'Déjà appuyé par un partenaire (RG-07)',
                default => $nonConcerne($oevs[$id]),
            };
        }

        return $motifs;
    }

    /**
     * Bénéficiaires pas encore choisis pour la session, avec leur score, filtrés et triés par urgence.
     *
     * Chaque filtre accepte une valeur ou une liste de valeurs (« l'une ou l'autre ») : plusieurs régions,
     * plusieurs vulnérabilités… Localité : « regions » (régions entières) et « provinces » (seulement ces
     * provinces dans leur région) ; une région dont des provinces sont cochées est limitée à ces provinces.
     */
    private function recherche(SessionParrainage $session, array $f): Builder
    {
        $liste = fn (string ...$cles) => collect($cles)->flatMap(fn ($cle) => (array) ($f[$cle] ?? []))
            ->filter(fn ($v) => $v !== null && $v !== '')->unique()->values()->all();
        $handicap = $liste('handicap');

        return Oev::beneficiaires()
            ->whereNotIn('oevs.id', BeneficiaireSession::where('session_parrainage_id', $session->id)->select('oev_id'))
            // Entièrement financés par une autre session de l'année : plus proposés (ni éligibles ni affichés)
            ->whereNotIn('oevs.id', $this->entierementFinances($session))
            ->select('oevs.*')
            // Score d'urgence enregistré sur la fiche (ScoreBesoinObserver), indexé
            ->selectRaw('oevs.score_besoin as score')
            ->when($liste('type'), fn ($q, $types) => $this->concernesPar($q, $types))
            ->tap(fn ($q) => $this->dansLesLocalites($q, $liste('regions', 'region_id'), $liste('provinces', 'province_id'), $liste('commune_id')))
            ->when($liste('sexe'), fn ($q, $v) => $q->whereIn('oevs.sexe', $v))
            ->when(isset($f['age_min']), fn ($q) => $q->whereDate('oevs.date_naissance', '<=', now()->subYears((int) $f['age_min'])))
            ->when(isset($f['age_max']), fn ($q) => $q->whereDate('oevs.date_naissance', '>', now()->subYears((int) $f['age_max'] + 1)))
            ->when($liste('statut'), fn ($q, $v) => $q->whereIn('oevs.statut', $v))
            // Au moins une des vulnérabilités choisies (liste JSON lue comme texte : PostgreSQL et SQLite)
            ->when($liste('vulnerabilite'), fn ($q, $codes) => $q->where(function ($q) use ($codes) {
                foreach ($codes as $code) {
                    $q->orWhereRaw('CAST(oevs.vulnerabilites AS TEXT) LIKE ?', ['%"' . $code . '"%']);
                }
            }))
            // Oui et non cochés ensemble : aucun filtre
            ->when(count($handicap) === 1, fn ($q) => $q->where('oevs.handicap', (bool) $handicap[0]))
            ->when($liste('niveau_priorite'), fn ($q, $v) => $q->whereIn('oevs.niveau_priorite', $v))
            ->when($liste('niveau_etude'), fn ($q, $v) => $q->whereIn('oevs.niveau_etude', $v))
            ->when($liste('groupe_population'), fn ($q, $v) => $q->whereIn('oevs.groupe_population', $v))
            ->when($liste('scolarite'), fn ($q, $situations) => $q->where(function ($q) use ($situations) {
                foreach ($situations as $situation) {
                    $q->orWhere(fn ($q) => match ($situation) {
                        self::SCOLARISE => $this->scolarises($q),
                        self::A_SCOLARISER => $this->aScolariser($q),
                        self::EN_FORMATION => $this->enFormation($q),
                        default => $q,
                    });
                }
            }))
            ->when($f['q'] ?? null, fn ($q, $terme) => $q->where(fn ($q) => $q
                ->whereLike('oevs.code', "%{$terme}%")->orWhereLike('oevs.nom', "%{$terme}%")
                ->orWhereLike('oevs.prenom', "%{$terme}%")->orWhereLike('oevs.etablissement_actuel', "%{$terme}%")))
            // Jamais financés cette année d'abord, puis les compléments (frais qui dépassaient le plafond)
            ->tap(function (Builder $q) use ($session) {
                $complements = $this->financementsAnterieurs($session)->select('oev_id')->toBase();
                $q->orderByRaw('case when oevs.id in (' . $complements->toSql() . ') then 1 else 0 end', $complements->getBindings());
            })
            ->orderByDesc('oevs.score_besoin')
            ->orderBy('oevs.date_naissance')
            ->orderBy('oevs.id');
    }

    /**
     * Localités choisies : régions entières, ou seulement certaines provinces de ces régions.
     * Une province cochée limite sa région à cette province ; sans choix, aucune restriction.
     */
    private function dansLesLocalites(Builder $requete, array $regions, array $provinces, array $communes = []): Builder
    {
        $provinces = Province::whereIn('id', $provinces)->get(['id', 'region_id']);
        $regionsEntieres = array_diff(array_map('intval', $regions), $provinces->pluck('region_id')->all());

        return $requete
            ->when($regionsEntieres || $provinces->isNotEmpty(), fn ($q) => $q->where(fn ($q) => $q
                ->whereIn('oevs.region_id', $regionsEntieres)
                ->orWhereIn('oevs.province_id', $provinces->pluck('id'))))
            ->when($communes, fn ($q) => $q->whereIn('oevs.commune_id', $communes));
    }

    /** Lignes retenues par les autres sessions de la même année, pour les natures de cette session. */
    private function financementsAnterieurs(SessionParrainage $session): Builder
    {
        return BeneficiaireSession::retenus()
            ->whereIn('type_appui', $session->naturesCouvertes())
            ->whereHas('session', fn ($q) => $q->where('annee', $session->annee)->whereKeyNot($session->id));
    }

    /**
     * OEV dont les frais sont entièrement couverts par l'État cette année (autres sessions) : le total versé
     * atteint les frais, ou les frais n'étaient pas connus. Ils ne sont plus proposés.
     */
    private function entierementFinances(SessionParrainage $session): Builder
    {
        return $this->financementsAnterieurs($session)
            ->select('oev_id')
            ->groupBy('oev_id')
            ->havingRaw('sum(montant_retenu) >= max(coalesce(frais_reels, 0)) or sum(case when frais_reels is null then 1 else 0 end) > 0');
    }

    /**
     * Montant déjà versé par l'État cette année (autres sessions, mêmes natures) aux OEV financés en partie.
     * Une session de rattrapage ne finance que le reste : frais − déjà versé, dans la limite de son plafond.
     *
     * @param  array<int>|null  $oevIds  OEV concernés (null : tous)
     * @return array<int, int> oev_id => FCFA déjà versés
     */
    public function dejaFinance(SessionParrainage $session, ?array $oevIds = null): array
    {
        return $this->financementsAnterieurs($session)
            ->when($oevIds !== null, fn ($q) => $q->whereIn('oev_id', $oevIds))
            ->selectRaw('oev_id, sum(montant_retenu) as total')
            ->groupBy('oev_id')
            ->pluck('total', 'oev_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** Frais restant à financer après ce que l'État a déjà versé (null si les frais sont inconnus). */
    public static function fraisRestants(?int $frais, int $dejaVerse): ?int
    {
        return $frais === null ? null : max(0, $frais - $dejaVerse);
    }

    /** Éligibles sans les filtres de recherche (aussi ceux déjà choisis pour la session). */
    private function baseEligibles(SessionParrainage $session): Builder
    {
        $natures = $session->naturesCouvertes();

        // Une autre session de l'année a déjà financé l'OEV pour la même nature : seul celui dont les frais
        // dépassaient le plafond (financé en partie) reste éligible, pour le complément (pas de double financement)
        $requete = $this->concernesPar(Oev::beneficiaires(), $natures)
            ->whereNotIn('oevs.id', $this->entierementFinances($session));

        // RG-07 : OEV déjà appuyés par un partenaire pour la même nature et la même année
        if ($session->exclure_deja_appuyes) {
            $requete->whereNotIn('oevs.id', AppuiPartenaire::where('annee', $session->annee)
                ->whereIn('nature_appui_id', NatureAppui::whereIn('code', $natures)->select('id'))
                ->select('oev_id'));
        }

        return $requete;
    }

    /**
     * OEV concernés par au moins une des natures : scolarisés ou d'âge scolaire à inscrire (scolaire),
     * en formation professionnelle en cours (formation).
     */
    private function concernesPar(Builder $requete, array $natures): Builder
    {
        return $requete->where(function ($q) use ($natures) {
            if (in_array(SessionParrainage::TYPE_SCOLAIRE, $natures, true)) {
                $q->orWhere(fn ($q) => $this->scolarises($q))->orWhere(fn ($q) => $this->aScolariser($q));
            }
            if (in_array(SessionParrainage::TYPE_FORMATION, $natures, true)) {
                $q->orWhere(fn ($q) => $this->enFormation($q));
            }
        });
    }

    private function scolarises(Builder $q): Builder
    {
        return $q->where('oevs.situation_scolaire', 'scolarise');
    }

    private function enFormation(Builder $q): Builder
    {
        return $q->where('oevs.formation_professionnelle', true)->where('oevs.formation_etat', 'en_cours');
    }

    /** Non scolarisé (ou déscolarisé, foyer coranique…), pas en formation, et d'âge scolaire. */
    private function aScolariser(Builder $q): Builder
    {
        return $q->where(fn ($q) => $q->whereNull('oevs.situation_scolaire')->orWhere('oevs.situation_scolaire', '!=', 'scolarise'))
            ->where(fn ($q) => $q->whereNull('oevs.formation_professionnelle')->orWhere('oevs.formation_professionnelle', false)
                ->orWhereNull('oevs.formation_etat')->orWhere('oevs.formation_etat', '!=', 'en_cours'))
            ->whereDate('oevs.date_naissance', '<=', now()->subYears(self::AGE_SCOLAIRE_MIN))
            ->whereDate('oevs.date_naissance', '>', now()->subYears(self::AGE_SCOLAIRE_MAX + 1));
    }

    /** Situation de l'OEV au regard de l'appui : scolarisé, à scolariser, en formation, ou null (aucune). */
    public static function situationDe(Oev $oev): ?string
    {
        $enFormation = $oev->formation_professionnelle && $oev->formation_etat === 'en_cours';

        return match (true) {
            $oev->situation_scolaire === 'scolarise' => self::SCOLARISE,
            $enFormation => self::EN_FORMATION,
            $oev->age() >= self::AGE_SCOLAIRE_MIN && $oev->age() <= self::AGE_SCOLAIRE_MAX => self::A_SCOLARISER,
            default => null,
        };
    }

    /** Nature d'appui d'un OEV dans la session : formation s'il est en formation (et que la session la couvre), sinon scolaire. */
    public static function typePour(Oev $oev, SessionParrainage $session): string
    {
        $natures = $session->naturesCouvertes();

        if (! in_array(SessionParrainage::TYPE_SCOLAIRE, $natures, true)) {
            return SessionParrainage::TYPE_FORMATION;
        }

        return in_array(SessionParrainage::TYPE_FORMATION, $natures, true) && self::situationDe($oev) === self::EN_FORMATION
            ? SessionParrainage::TYPE_FORMATION
            : SessionParrainage::TYPE_SCOLAIRE;
    }

    /**
     * Frais de l'OEV pour la session : frais de scolarité de la fiche pour un enfant scolarisé ; montant
     * estimatif de la session pour un enfant à inscrire ou en formation (null s'il n'est pas encore saisi :
     * le plafond est alors retenu).
     *
     * @return array{frais: ?int, estime: bool}
     */
    public static function fraisPour(Oev $oev, SessionParrainage $session, ?string $type = null): array
    {
        $type ??= self::typePour($oev, $session);

        return match (true) {
            // Frais de formation connus (saisis au suivi), sinon le montant estimatif de la session
            $type === SessionParrainage::TYPE_FORMATION => $oev->frais_formation !== null
                ? ['frais' => $oev->frais_formation, 'estime' => false]
                : ['frais' => $session->estimation_formation, 'estime' => true],
            $oev->situation_scolaire === 'scolarise' => ['frais' => $oev->frais_scolarite, 'estime' => false],
            default => ['frais' => $session->estimation_scolarisation, 'estime' => true],
        };
    }

    /**
     * Coût estimé des OEV éligibles correspondant aux filtres, par situation : nombre d'OEV, cumul des frais
     * (scolarité de la fiche ou montants estimatifs) et montant pris en charge (frais plafonnés ; plafond
     * quand les frais manquent).
     *
     * @return array{groupes: array<string, array{nombre: int, frais: int, sans_frais: int, pris_en_charge: int}>, nombre: int, frais: int, pris_en_charge: int}
     */
    public function estimationCout(SessionParrainage $session, Builder $eligibles): array
    {
        $natures = $session->naturesCouvertes();
        $couvreScolaire = in_array(SessionParrainage::TYPE_SCOLAIRE, $natures, true);
        $couvreFormation = in_array(SessionParrainage::TYPE_FORMATION, $natures, true);

        // Mêmes règles que typePour(), fraisPour(), fraisRestants() et BeneficiaireSession::montantPour(),
        // calculées par la base en une requête (une ligne par situation) au lieu d'un parcours de tous les éligibles
        $scolarise = "oevs.situation_scolaire = 'scolarise'";
        $enFormation = "COALESCE(oevs.situation_scolaire, '') <> 'scolarise' AND oevs.formation_professionnelle = ? AND oevs.formation_etat = 'en_cours'";
        [$typeFormation, $liaisonsType] = match (true) {
            ! $couvreScolaire => ['1 = 1', []],
            $couvreFormation => [$enFormation, [true]],
            default => ['1 = 0', []],
        };
        $situations = (clone $eligibles)->reorder()
            ->select('oevs.id')
            ->selectRaw("CASE WHEN {$typeFormation} THEN ? WHEN {$scolarise} THEN ? ELSE ? END AS groupe",
                [...$liaisonsType, self::EN_FORMATION, self::SCOLARISE, self::A_SCOLARISER])
            ->selectRaw("CASE WHEN {$typeFormation} THEN COALESCE(oevs.frais_formation, ?) WHEN {$scolarise} THEN oevs.frais_scolarite ELSE ? END AS frais",
                [...$liaisonsType, $session->estimation_formation, $session->estimation_scolarisation]);
        $dejaVerse = $this->financementsAnterieurs($session)->toBase()
            ->selectRaw('oev_id, SUM(montant_retenu) AS total')->groupBy('oev_id');
        // Frais restant à financer après ce que l'État a déjà versé (null si inconnus)
        $restants = DB::query()->fromSub($situations->toBase(), 'e')
            ->leftJoinSub($dejaVerse, 'd', 'd.oev_id', '=', 'e.id')
            ->selectRaw('e.groupe, CASE WHEN e.frais IS NULL THEN NULL WHEN e.frais - COALESCE(d.total, 0) < 0 THEN 0 ELSE e.frais - COALESCE(d.total, 0) END AS frais');
        $plafond = $session->plafond_beneficiaire;
        [$prisEnCharge, $liaisonsPlafond] = $plafond === null
            ? ['COALESCE(r.frais, 0)', []]
            : ['CASE WHEN r.frais IS NULL OR r.frais > ? THEN ? ELSE r.frais END', [$plafond, $plafond]];
        $totaux = DB::query()->fromSub($restants, 'r')
            ->selectRaw("r.groupe, COUNT(*) AS nombre, COALESCE(SUM(r.frais), 0) AS frais,
                SUM(CASE WHEN r.frais IS NULL THEN 1 ELSE 0 END) AS sans_frais, COALESCE(SUM({$prisEnCharge}), 0) AS pris_en_charge", $liaisonsPlafond)
            ->groupBy('r.groupe')
            ->get()
            ->keyBy('groupe');

        $groupes = [];
        foreach (array_keys(self::SITUATIONS) as $groupe) {
            $ligne = $totaux[$groupe] ?? null;
            $groupes[$groupe] = [
                'nombre' => (int) ($ligne->nombre ?? 0),
                'frais' => (int) ($ligne->frais ?? 0),
                'sans_frais' => (int) ($ligne->sans_frais ?? 0),
                'pris_en_charge' => (int) ($ligne->pris_en_charge ?? 0),
            ];
        }

        return [
            'groupes' => $groupes,
            'nombre' => array_sum(array_column($groupes, 'nombre')),
            'frais' => array_sum(array_column($groupes, 'frais')),
            'pris_en_charge' => array_sum(array_column($groupes, 'pris_en_charge')),
        ];
    }

    /**
     * Montants estimatifs de la session ; les lignes déjà ajoutées dont les frais viennent de l'estimation
     * (non corrigés à la main) sont recalculées.
     *
     * @return int nombre de lignes recalculées
     */
    public function modifierEstimations(SessionParrainage $session, ?int $scolarisation, ?int $formation, User $utilisateur): int
    {
        return DB::transaction(function () use ($session, $scolarisation, $formation, $utilisateur) {
            $session->update(['estimation_scolarisation' => $scolarisation, 'estimation_formation' => $formation, 'updated_by' => $utilisateur->id]);

            $lignes = $session->beneficiaires()->where('frais_estimes', true)->get();
            foreach ($lignes as $ligne) {
                $frais = $ligne->type_appui === SessionParrainage::TYPE_FORMATION ? $formation : $scolarisation;
                $ligne->update(['frais_reels' => $frais, 'montant_retenu' => BeneficiaireSession::montantPour($frais, $session->plafond_beneficiaire)]);
            }

            JournalActivite::consigner('session_parrainage.estimations', $session, sprintf(
                'Montants estimatifs : scolarisation %s, formation %s',
                $scolarisation === null ? 'non renseigné' : Montant::fcfa($scolarisation),
                $formation === null ? 'non renseigné' : Montant::fcfa($formation),
            ), ['lignes_recalculees' => $lignes->count()], $utilisateur);

            return $lignes->count();
        });
    }

    /* ---------------------------------------------------------------- Liste de la session */

    /**
     * Ajoute des OEV éligibles à la session : tous retenus, ou aucun. Avec une enveloppe (ou des quotas par
     * région), l'ajout est refusé en entier s'il la dépasserait (il n'y a pas de liste d'attente). Les OEV
     * non éligibles sont ignorés.
     *
     * @param  array<int, int>  $oevIds
     * @return array{retenus: int, refus: string|null} refus : raison du refus (rien n'est ajouté)
     */
    public function ajouter(SessionParrainage $session, array $oevIds, User $utilisateur): array
    {
        if (! $oevIds) {
            return ['retenus' => 0, 'refus' => null];
        }

        return DB::transaction(function () use ($session, $oevIds, $utilisateur) {
            $budget = $this->budget($session);
            $dejaFinance = $this->dejaFinance($session, $oevIds);
            $lignes = [];
            $ajout = ['engage' => 0, 'regions' => []];

            foreach ($this->eligibles($session)->whereIn('oevs.id', $oevIds)->get() as $oev) {
                $type = self::typePour($oev, $session);
                ['frais' => $frais, 'estime' => $estime] = self::fraisPour($oev, $session, $type);
                // Complément : seul le reste à payer est financé
                $frais = self::fraisRestants($frais, $dejaFinance[$oev->id] ?? 0);
                $montant = BeneficiaireSession::montantPour($frais, $session->plafond_beneficiaire);
                $lignes[] = [
                    'oev_id' => $oev->id,
                    'type_appui' => $type,
                    'statut' => BeneficiaireSession::RETENU,
                    'score' => (int) $oev->score,
                    'region_id' => $oev->region_id,
                    // École ou centre à payer (suit la fiche jusqu'au paiement)
                    'etablissement_id' => PaiementsParrainage::etablissementDe($oev, $type),
                    'frais_reels' => $frais,
                    'frais_estimes' => $estime,
                    'montant_retenu' => $montant,
                    'created_by' => $utilisateur->id,
                ];
                $ajout['engage'] += $montant;
                $ajout['regions'][$oev->region_id] = ($ajout['regions'][$oev->region_id] ?? 0) + $montant;
            }

            if ($refus = $this->depassement($session, $budget, $ajout)) {
                return ['retenus' => 0, 'refus' => $refus];
            }
            foreach ($lignes as $ligne) {
                $session->beneficiaires()->create($ligne);
            }
            if ($lignes) {
                JournalActivite::consigner('session_parrainage.selection', $session,
                    'Sélection : ' . count($lignes) . ' OEV retenu(s) pour ' . Montant::fcfa($ajout['engage']), ['retenus' => count($lignes)], $utilisateur);
            }

            return ['retenus' => count($lignes), 'refus' => null];
        });
    }

    /**
     * Raison pour laquelle l'ajout $ajout (montant total et par région) dépasserait l'enveloppe ou un quota ;
     * null s'il tient dans le budget.
     *
     * @param  array{engage: int, regions: array<int, int>}  $budget  montants déjà engagés
     * @param  array{engage: int, regions: array<int, int>}  $ajout  montants à ajouter
     */
    public function depassement(SessionParrainage $session, array $budget, array $ajout): ?string
    {
        if ($session->aUneEnveloppe() && $budget['engage'] + $ajout['engage'] > $session->enveloppe) {
            $reste = $session->enveloppe - $budget['engage'];

            return 'ces OEV coûtent ' . Montant::fcfa($ajout['engage']) . ' et il reste ' . Montant::fcfa(max(0, $reste))
                . ' sur l’enveloppe (dépassement de ' . Montant::fcfa($ajout['engage'] - $reste) . '). Retirez des OEV ou affinez les filtres.';
        }
        if ($session->quotas_actifs) {
            foreach ($ajout['regions'] as $regionId => $montant) {
                $quota = (int) ($session->quotas->firstWhere('region_id', $regionId)?->montant ?? 0);
                $reste = $quota - ($budget['regions'][$regionId] ?? 0);
                if ($montant > $reste) {
                    $nom = Region::whereKey($regionId)->value('nom') ?? 'sans région';

                    return "les OEV de la région {$nom} coûtent " . Montant::fcfa($montant) . ' et il reste ' . Montant::fcfa(max(0, $reste))
                        . ' sur son quota. Retirez des OEV de cette région ou affinez les filtres.';
                }
            }
        }

        return null;
    }

    /** Frais réels corrigés : le montant retenu est recalculé (min(frais, plafond)). */
    public function modifierFrais(SessionParrainage $session, BeneficiaireSession $ligne, ?int $frais, User $utilisateur): ?string
    {
        $montant = BeneficiaireSession::montantPour($frais, $session->plafond_beneficiaire);

        if ($montant > $ligne->montant_retenu) {
            $budget = $this->budget($session);
            $budget['engage'] -= $ligne->montant_retenu;
            $budget['regions'][$ligne->region_id] = ($budget['regions'][$ligne->region_id] ?? 0) - $ligne->montant_retenu;
            if (! $this->tientDansLeBudget($session, $budget, $ligne->region_id, $montant)) {
                return 'Avec ces frais, ' . $this->messageHorsBudget($session, $ligne, $montant);
            }
        }

        // Frais saisis pour cet OEV : ils ne suivent plus les montants estimatifs de la session
        $ligne->update(['frais_reels' => $frais, 'frais_estimes' => false, 'montant_retenu' => $montant]);
        JournalActivite::consigner('session_parrainage.selection', $session,
            "Frais de l’OEV {$ligne->oev->reference()} : " . ($frais === null ? 'non renseignés' : Montant::fcfa($frais)), [], $utilisateur);

        return null;
    }

    public function retirer(SessionParrainage $session, BeneficiaireSession $ligne, User $utilisateur): void
    {
        $reference = $ligne->oev->reference();
        $ligne->delete();
        JournalActivite::consigner('session_parrainage.selection', $session, "OEV {$reference} retiré de la sélection", [], $utilisateur);
    }

    /**
     * Montants engagés (retenus) au total et par région, avec l'enveloppe et les quotas de la session.
     *
     * @return array{engage: int, regions: array<int, int>}
     */
    public function budget(SessionParrainage $session): array
    {
        // Sommes calculées par la base : rien n'est chargé ligne à ligne, quelle que soit la taille de la liste
        $parRegion = $session->beneficiaires()->retenus()
            ->selectRaw('region_id, sum(montant_retenu) as total')
            ->groupBy('region_id')
            ->pluck('total', 'region_id')
            ->map(fn ($total) => (int) $total);

        return [
            'engage' => (int) $parRegion->sum(),
            'regions' => $parRegion->all(),
        ];
    }

    private function tientDansLeBudget(SessionParrainage $session, array $budget, ?int $regionId, int $montant): bool
    {
        // Enveloppe renseignée : jamais dépassée (sans enveloppe, aucune limite globale)
        if ($session->aUneEnveloppe() && $budget['engage'] + $montant > $session->enveloppe) {
            return false;
        }
        if ($session->quotas_actifs) {
            // Quotas actifs : une région sans quota ne reçoit rien
            $quota = (int) ($session->quotas->firstWhere('region_id', $regionId)?->montant ?? 0);

            return ($budget['regions'][$regionId] ?? 0) + $montant <= $quota;
        }

        return true;
    }

    private function messageHorsBudget(SessionParrainage $session, BeneficiaireSession $ligne, ?int $montant = null): string
    {
        $montant ??= $ligne->montant_retenu;

        return $session->quotas_actifs
            ? 'le quota de la région ' . ($ligne->region?->nom ?? '') . ' ne permet pas de retenir ' . Montant::fcfa($montant) . ' de plus.'
            : 'l’enveloppe de la session ne permet pas de retenir ' . Montant::fcfa($montant) . ' de plus.';
    }

    /* ---------------------------------------------------------------- SourceSelection */

    public function disponible(): bool
    {
        return true;
    }

    public function eligiblesParRegion(SessionParrainage $session): array
    {
        return $this->baseEligibles($session)
            ->whereNotNull('oevs.region_id')
            ->selectRaw('oevs.region_id, count(*) as total')
            ->groupBy('oevs.region_id')
            ->pluck('total', 'region_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** La liste n'est définitive qu'une fois la session validée : avant, montant et liste sont « non disponibles ». */
    private function listeArretee(SessionParrainage $session): bool
    {
        return $session->etat !== SessionParrainage::ETAT_EN_COURS;
    }

    public function montantEngage(SessionParrainage $session): ?int
    {
        return $this->listeArretee($session) ? (int) $session->beneficiaires()->retenus()->sum('montant_retenu') : null;
    }

    public function listeDefinitive(SessionParrainage $session): Collection
    {
        if (! $this->listeArretee($session)) {
            return collect();
        }

        // Lignes brutes (pas de modèles) : la liste peut compter des dizaines de milliers de bénéficiaires
        return $session->beneficiaires()->retenus()->orderByDesc('score')->orderBy('id')->toBase()
            ->get(['oev_id', 'type_appui', 'etablissement_id', 'frais_reels', 'montant_retenu'])
            ->map(fn ($b) => [
                'oev_id' => (int) $b->oev_id,
                'type_appui' => $b->type_appui,
                'etablissement_id' => self::entier($b->etablissement_id),
                'frais_reels' => self::entier($b->frais_reels ?? $b->montant_retenu),
                'montant_retenu' => self::entier($b->montant_retenu),
            ]);
    }

    /** Même conversion que le cast « integer » du modèle : null reste null. */
    private static function entier(mixed $valeur): ?int
    {
        return $valeur === null ? null : (int) $valeur;
    }

    /** Établissements payés par au moins une session (écoles et centres de formation du référentiel). */
    public function etablissements(): Collection
    {
        return Ecole::query()
            ->whereIn('ecoles.id', BeneficiaireSession::whereNotNull('etablissement_id')->select('etablissement_id'))
            ->join('communes as c', 'c.id', '=', 'ecoles.commune_id')
            ->join('provinces as p', 'p.id', '=', 'c.province_id')
            ->toBase()
            ->get(['ecoles.id', 'ecoles.nom', 'ecoles.nature', 'ecoles.statut', 'p.region_id', 'c.province_id', 'ecoles.commune_id', 'ecoles.banque', 'ecoles.rib'])
            ->mapWithKeys(fn ($e) => [(int) $e->id => [
                'id' => (int) $e->id,
                'nom' => $e->nom,
                'type' => trim((Ecole::NATURES[$e->nature] ?? '') . ($e->statut ? ' · ' . (Ecole::STATUTS[$e->statut] ?? $e->statut) : '')),
                'region_id' => (int) $e->region_id,
                'province_id' => (int) $e->province_id,
                'commune_id' => (int) $e->commune_id,
                'banque' => $e->banque,
                'rib' => $e->rib === '' ? null : $e->rib,
            ]]);
    }

    public function naturesAppuiEtat(int $oevId, string $annee): array
    {
        return BeneficiaireSession::retenus()
            ->where('oev_id', $oevId)
            ->whereHas('session', fn ($q) => $q->where('annee', $annee)->where('etat', '!=', SessionParrainage::ETAT_EN_COURS))
            ->distinct()
            ->pluck('type_appui')
            ->all();
    }

    public function requeteListeDefinitive(Collection $sessions): QueryBuilder
    {
        $arretees = $sessions->filter(fn (SessionParrainage $s) => $this->listeArretee($s))->pluck('id')->all();

        return BeneficiaireSession::retenus()->whereIn('session_parrainage_id', $arretees)->toBase()
            ->select(['oev_id', 'type_appui', 'montant_retenu']);
    }

    public function requeteResultatsScolaires(string $annee): QueryBuilder
    {
        return SuiviParrainage::where('annee', $annee)->where('type_appui', SessionParrainage::TYPE_SCOLAIRE)->toBase()->select(['oev_id', 'resultat']);
    }

    /** Résultats scolaires du module « Suivi des parrainés » (appui scolaire seulement). */
    public function resultatsScolaires(string $annee): Collection
    {
        return SuiviParrainage::where('annee', $annee)
            ->where('type_appui', SessionParrainage::TYPE_SCOLAIRE)
            ->get(['oev_id', 'annee', 'resultat'])
            ->map(fn (SuiviParrainage $s) => ['oev_id' => $s->oev_id, 'annee' => $s->annee, 'resultat' => $s->resultat])
            ->values();
    }

    /**
     * Dernier suivi de chacun des OEV donnés (pour éclairer un nouveau parrainage).
     *
     * @param  iterable<Oev>  $oevs
     * @return Collection<int, SuiviParrainage> indexés par oev_id
     */
    public function derniersSuivis(iterable $oevs): Collection
    {
        $ids = collect($oevs)->pluck('id')->all();

        return $ids === [] ? collect() : SuiviParrainage::whereIn('oev_id', $ids)
            ->orderBy('annee')->orderBy('id')
            ->get()
            ->keyBy('oev_id');
    }
}
