<?php

namespace App\Services\Parrainage;

use App\Models\AppuiPartenaire;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\Province;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Données des deux extractions du module « Pilotage » :
 *  - liste pour paiement (services financiers, paiement hors système), à partir de la liste définitive ;
 *  - liste d'OEV à proposer à un parrain, anonymisée par défaut.
 * La mise en forme (Excel, PDF) est faite par FichiersExtraction.
 */
class ExtractionsParrainage
{
    /** Ordre de tri des priorités : les cas les plus urgents d'abord. */
    private const ORDRE_PRIORITE = ['eleve' => 0, 'moyen' => 1, 'faible' => 2];

    /**
     * Liste pour paiement, feuille « Par établissement » : colonnes au choix. Le nombre d'OEV et le montant
     * sont toujours extraits (ils portent les totaux).
     */
    public const COLONNES_ETABLISSEMENT = [
        'region' => 'Région', 'province' => 'Province', 'etablissement' => 'Établissement', 'type' => 'Type d’établissement',
        'banque' => 'Banque', 'code_banque' => 'Code banque', 'code_guichet' => 'Code guichet', 'numero_compte' => 'N° de compte',
        'cle_rib' => 'Clé RIB', 'titulaire' => 'Titulaire', 'rib' => 'RIB', 'nombre_oev' => 'Nombre d’OEV', 'montant' => 'Montant total (FCFA)',
    ];

    public const COLONNES_ETABLISSEMENT_OBLIGATOIRES = ['nombre_oev', 'montant'];

    /** Feuille « Nominatif » : colonnes au choix ; le montant retenu est toujours extrait. */
    public const COLONNES_NOMINATIF = [
        'etablissement' => 'Établissement', 'code' => 'Code OEV', 'nom' => 'Nom', 'prenom' => 'Prénom', 'sexe' => 'Sexe',
        'date_naissance' => 'Date de naissance', 'region' => 'Région', 'province' => 'Province', 'commune' => 'Commune',
        'type_appui' => 'Type d’appui', 'classe' => 'Classe ou filière', 'tuteur' => 'Parent / tuteur', 'telephone_tuteur' => 'Téléphone du tuteur',
        'frais_reels' => 'Frais réels (FCFA)', 'montant_retenu' => 'Montant retenu (FCFA)',
    ];

    public const COLONNES_NOMINATIF_OBLIGATOIRES = ['montant_retenu'];

    public const NOMINATIF_PAR_DEFAUT = ['etablissement', 'nom', 'prenom', 'sexe', 'date_naissance', 'classe', 'frais_reels', 'montant_retenu'];

    /**
     * Colonnes choisies, dans l'ordre du catalogue, avec les obligatoires ; sans choix : toutes (ou celles par défaut).
     *
     * @return array<string, string> clé => libellé
     */
    public static function colonnesChoisies(array $catalogue, ?array $choix, array $obligatoires, ?array $parDefaut = null): array
    {
        $cles = $choix === null ? ($parDefaut ?? array_keys($catalogue)) : $choix;

        return array_intersect_key($catalogue, array_flip(array_merge(array_map('strval', $cles), $obligatoires)));
    }

    /** @var array<string, array<int, string|null>> noms des régions / provinces déjà lus */
    private array $nomsLocalites = [];

    public function __construct(private readonly SourceSelection $source)
    {
    }

    /**
     * Liste pour paiement d'une session au moins « Validée », limitée à la zone de l'utilisateur.
     *
     * @param  array{region_id?: int|null, province_id?: int|null, commune_id?: int|null, etablissement_id?: int|null, type_appui?: string}  $filtres
     * @return array{etablissements: Collection, nominatif: Collection, total_oev: int, total_montant: int, disponible: bool}
     *   etablissements : une ligne par établissement (avec rib_manquant), triées par région, province, nom ;
     *   nominatif : une ligne par bénéficiaire, triées par établissement puis nom.
     */
    public function paiement(SessionParrainage $session, User $utilisateur, array $filtres): array
    {
        $types = PilotageParrainage::naturesDuType($filtres['type_appui'] ?? '');
        $liste = $this->source->listeDefinitive($session)
            ->filter(fn (array $b) => ! $types || ! isset($b['type_appui']) || in_array($b['type_appui'], $types, true))
            ->when($filtres['etablissement_id'] ?? null, fn ($l, $id) => $l->where('etablissement_id', $id));

        $oevs = Oev::with(['region', 'province', 'commune'])
            ->whereIn('id', $liste->pluck('oev_id'))
            ->dansLePerimetreDe($utilisateur)
            ->when($filtres['region_id'] ?? null, fn ($q, $id) => $q->where('region_id', $id))
            ->when($filtres['province_id'] ?? null, fn ($q, $id) => $q->where('province_id', $id))
            ->when($filtres['commune_id'] ?? null, fn ($q, $id) => $q->where('commune_id', $id))
            ->get()
            ->keyBy('id');
        $etablissements = $this->source->etablissements()->keyBy('id');
        $liste = $liste->filter(fn (array $b) => $oevs->has($b['oev_id']));

        $nominatif = $liste->map(function (array $b) use ($oevs, $etablissements) {
            $oev = $oevs[$b['oev_id']];

            return [
                'etablissement' => $etablissements[$b['etablissement_id']]['nom'] ?? 'Établissement non renseigné',
                'code' => $oev->code,
                'nom' => $oev->nom,
                'prenom' => $oev->prenom,
                'sexe' => Oev::SEXES[$oev->sexe] ?? $oev->sexe,
                'date_naissance' => $oev->date_naissance?->format('d/m/Y'),
                'region' => $oev->region?->nom,
                'province' => $oev->province?->nom,
                'commune' => $oev->commune?->nom,
                'type_appui' => SessionParrainage::TYPES_APPUI[$b['type_appui'] ?? ''] ?? '',
                'classe' => $oev->classe ?: ($oev->formation_filiere ?: '—'),
                'tuteur' => trim($oev->nom_tuteur . ' ' . $oev->prenom_tuteur),
                'telephone_tuteur' => $oev->contact_tuteur,
                'frais_reels' => (int) $b['frais_reels'],
                'montant_retenu' => (int) $b['montant_retenu'],
            ];
        })->sortBy([['etablissement', 'asc'], ['nom', 'asc'], ['prenom', 'asc']])->values();

        $parEtablissement = $liste->groupBy(fn (array $b) => $b['etablissement_id'] ?? 0)->map(function (Collection $beneficiaires, $id) use ($etablissements, $oevs) {
            $etab = $etablissements[$id] ?? null;
            $premier = $oevs[$beneficiaires->first()['oev_id']];
            $rib = collect(['code_banque', 'code_guichet', 'numero_compte', 'cle_rib'])->map(fn ($champ) => $etab[$champ] ?? null);

            return [
                // Localisation de l'établissement, à défaut celle du premier bénéficiaire
                'region' => $etab ? ($this->nomLocalite('region', $etab['region_id'] ?? null) ?? $premier->region?->nom) : $premier->region?->nom,
                'province' => $etab ? ($this->nomLocalite('province', $etab['province_id'] ?? null) ?? $premier->province?->nom) : $premier->province?->nom,
                'etablissement' => $etab['nom'] ?? 'Établissement non renseigné',
                'type' => $etab['type'] ?? null,
                'banque' => $etab['banque'] ?? null,
                'code_banque' => $etab['code_banque'] ?? null,
                'code_guichet' => $etab['code_guichet'] ?? null,
                'numero_compte' => $etab['numero_compte'] ?? null,
                'cle_rib' => $etab['cle_rib'] ?? null,
                'titulaire' => $etab['titulaire'] ?? null,
                'nombre_oev' => $beneficiaires->count(),
                'montant' => (int) $beneficiaires->sum('montant_retenu'),
                'rib_manquant' => $rib->contains(fn ($v) => $v === null || $v === ''),
            ];
        })->sortBy([['region', 'asc'], ['province', 'asc'], ['etablissement', 'asc']])->values();

        return [
            'etablissements' => $parEtablissement,
            'nominatif' => $nominatif,
            'total_oev' => $nominatif->count(),
            'total_montant' => (int) $nominatif->sum('montant_retenu'),
            'disponible' => $this->source->disponible(),
        ];
    }

    /**
     * Lignes de l'onglet « Par établissement » avec les sous-totaux par province et par région, puis le total général.
     *
     * @return Collection<int, array> chaque ligne a un « niveau » : etablissement, province, region ou general
     */
    public static function lignesAvecTotaux(Collection $etablissements): Collection
    {
        $lignes = collect();
        foreach ($etablissements->groupBy('region') as $region => $parRegion) {
            foreach ($parRegion->groupBy('province') as $province => $parProvince) {
                $parProvince->each(fn ($l) => $lignes->push($l + ['niveau' => 'etablissement']));
                $lignes->push(['niveau' => 'province', 'libelle' => "Total province : {$province}", 'nombre_oev' => $parProvince->sum('nombre_oev'), 'montant' => $parProvince->sum('montant')]);
            }
            $lignes->push(['niveau' => 'region', 'libelle' => "Total région : {$region}", 'nombre_oev' => $parRegion->sum('nombre_oev'), 'montant' => $parRegion->sum('montant')]);
        }
        $lignes->push(['niveau' => 'general', 'libelle' => 'Total général', 'nombre_oev' => $etablissements->sum('nombre_oev'), 'montant' => $etablissements->sum('montant')]);

        return $lignes;
    }

    /**
     * Zone et natures proposées par défaut pour un parrain : ses zones d'intervention, les natures de ses engagements.
     *
     * @return array{zones: Collection, natures: array<int>}
     */
    public function preferencesParrain(Parrain $parrain): array
    {
        return [
            'zones' => $parrain->zones,
            'natures' => $parrain->engagements->flatMap(fn ($e) => $e->natures_appui ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ];
    }

    /**
     * OEV à proposer à un parrain, par ordre de priorité.
     *
     * Localité : la zone d'intervention du parrain (si « zone_parrain »), complétée par les régions et
     * provinces cochées (« regions », « provinces ») quand la zone ne suffit pas. Les autres filtres
     * acceptent plusieurs valeurs (« l'une ou l'autre »).
     *
     * @param  array  $f  source (« attente » : liste d'attente de la session ; « eligibles » : OEV sans appui de
     *                    cette nature pour l'année), session, annee, nature, zone_parrain, regions, provinces,
     *                    sexe, statut, vulnerabilite, niveau_priorite, niveau_etude, handicap, age_min, age_max
     * @return Collection<int, Oev>
     */
    public function listeParrain(Parrain $parrain, User $utilisateur, array $f): Collection
    {
        $liste = fn (string $cle) => array_values(array_filter((array) ($f[$cle] ?? []), fn ($v) => $v !== null && $v !== ''));
        $handicap = $liste('handicap');

        $requete = Oev::beneficiaires()
            ->with(['commune', 'province', 'region'])
            ->dansLePerimetreDe($utilisateur)
            ->tap(fn ($q) => $this->dansLesLocalites($q, $f['zone_parrain'] ? $parrain : null, $liste('regions'), $liste('provinces')))
            ->when($liste('sexe'), fn ($q, $v) => $q->whereIn('sexe', $v))
            ->when($liste('statut'), fn ($q, $v) => $q->whereIn('statut', $v))
            ->when($liste('niveau_priorite'), fn ($q, $v) => $q->whereIn('niveau_priorite', $v))
            ->when($liste('niveau_etude'), fn ($q, $v) => $q->whereIn('niveau_etude', $v))
            ->when(count($handicap) === 1, fn ($q) => $q->where('handicap', (bool) $handicap[0]))
            ->when($liste('vulnerabilite'), fn ($q, $codes) => $q->where(function ($q) use ($codes) {
                foreach ($codes as $code) {
                    $q->orWhereRaw('CAST(vulnerabilites AS TEXT) LIKE ?', ['%"' . $code . '"%']);
                }
            }))
            ->when(isset($f['age_min']), fn ($q) => $q->whereDate('date_naissance', '<=', now()->subYears((int) $f['age_min'])))
            ->when(isset($f['age_max']), fn ($q) => $q->whereDate('date_naissance', '>', now()->subYears((int) $f['age_max'] + 1)));

        if ($f['source'] === 'attente') {
            $attente = $f['session'] ? $this->source->listeAttente($f['session'])->sortBy('rang')->values() : collect();
            $rangs = $attente->pluck('rang', 'oev_id');
            $besoins = $attente->pluck('besoin_estime', 'oev_id');

            return $requete->whereIn('id', $rangs->keys())->get()
                ->each(fn (Oev $oev) => $oev->setAttribute('besoin_estime', $besoins[$oev->id] ?? $oev->frais_scolarite))
                ->sortBy(fn (Oev $oev) => $rangs[$oev->id])
                ->values();
        }

        if ($f['nature']) {
            $requete->whereNotIn('id', AppuiPartenaire::where('annee', $f['annee'])->where('nature_appui_id', $f['nature']->id)->select('oev_id'));
            // Nature financée par l'État : les bénéficiaires des sessions de l'année sont aussi exclus
            if ($f['nature']->scolaire && $this->source->disponible()) {
                $etat = SessionParrainage::where('annee', $f['annee'])->get()
                    ->flatMap(fn ($s) => $this->source->listeDefinitive($s))
                    ->filter(fn ($b) => ($b['type_appui'] ?? $f['nature']->code) === $f['nature']->code)
                    ->pluck('oev_id');
                $requete->whereNotIn('id', $etat->all());
            }
        }

        return $requete->get()
            ->each(fn (Oev $oev) => $oev->setAttribute('besoin_estime', $oev->frais_scolarite))
            ->sortBy([
                fn (Oev $a, Oev $b) => (self::ORDRE_PRIORITE[$a->niveau_priorite] ?? 3) <=> (self::ORDRE_PRIORITE[$b->niveau_priorite] ?? 3),
                fn (Oev $a, Oev $b) => strcmp($a->nom . $a->prenom, $b->nom . $b->prenom),
            ])
            ->values();
    }

    /**
     * Données proposées pour la liste d'un parrain : clé => [libellé, valeur, identifiante].
     * Les données identifiantes (nom, code, date de naissance, tuteur…) ne sont extraites que par le niveau
     * central, après confirmation ; les initiales sont la première lettre du nom et du prénom (ex : « OA »
     * pour OUEDRAOGO Awa).
     *
     * @return array<string, array{0: string, 1: callable(Oev): mixed, 2: bool}>
     */
    public static function donneesListeParrain(): array
    {
        return [
            'initiales' => ['Initiales', fn (Oev $o) => $o->initiales(), false],
            'code' => ['Code OEV', fn (Oev $o) => $o->code, true],
            'nom' => ['Nom', fn (Oev $o) => $o->nom, true],
            'prenom' => ['Prénom', fn (Oev $o) => $o->prenom, true],
            'date_naissance' => ['Date de naissance', fn (Oev $o) => $o->date_naissance?->format('d/m/Y'), true],
            'age' => ['Âge', fn (Oev $o) => $o->age(), false],
            'sexe' => ['Sexe', fn (Oev $o) => Oev::SEXES[$o->sexe] ?? $o->sexe, false],
            'statut' => ['Statut OEV', fn (Oev $o) => Oev::STATUTS[$o->statut] ?? $o->statut, false],
            'vulnerabilites' => ['Vulnérabilités', fn (Oev $o) => implode(', ', $o->libelles('vulnerabilites', Oev::VULNERABILITES)), false],
            'handicap' => ['Handicap', fn (Oev $o) => $o->handicap ? 'Oui' : 'Non', false],
            'region' => ['Région', fn (Oev $o) => $o->region?->nom, false],
            'province' => ['Province', fn (Oev $o) => $o->province?->nom, false],
            'commune' => ['Commune', fn (Oev $o) => $o->commune?->nom, false],
            'cycle' => ['Cycle ou filière', fn (Oev $o) => $o->formation_professionnelle
                ? 'Formation professionnelle' . ($o->formation_filiere ? " ({$o->formation_filiere})" : '')
                : (Oev::NIVEAUX_ETUDE[$o->niveau_etude] ?? 'Non scolarisé ou non précisé'), false],
            'classe' => ['Classe', fn (Oev $o) => Oev::toutesLesClasses()[$o->classe] ?? $o->classe, false],
            'besoin' => ['Besoin financier estimé (FCFA)', fn (Oev $o) => (int) $o->besoin_estime, false],
            'priorite' => ['Priorité', fn (Oev $o) => Oev::NIVEAUX[$o->niveau_priorite] ?? 'Non précisée', false],
            'tuteur' => ['Parent / tuteur', fn (Oev $o) => trim($o->nom_tuteur . ' ' . $o->prenom_tuteur), true],
            'telephone_tuteur' => ['Téléphone du tuteur', fn (Oev $o) => $o->contact_tuteur, true],
        ];
    }

    /** Liste anonymisée par défaut. */
    public const DONNEES_PARRAIN_PAR_DEFAUT = ['initiales', 'age', 'sexe', 'commune', 'cycle', 'besoin', 'priorite'];

    /** Aperçu à l'écran (agents) : l'enfant est désigné par son nom. */
    public const DONNEES_APERCU = ['code', 'nom', 'prenom', 'age', 'sexe', 'commune', 'cycle', 'besoin', 'priorite'];

    /**
     * Colonnes demandées, dans l'ordre du catalogue.
     *
     * @return array<string, callable(Oev): mixed> libellé => valeur
     */
    public static function colonnesListeParrain(array $cles): array
    {
        return collect(self::donneesListeParrain())
            ->only($cles)
            ->mapWithKeys(fn (array $donnee) => [$donnee[0] => $donnee[1]])
            ->all();
    }

    /** Les données demandées comportent-elles des éléments d'identité de l'enfant ? */
    public static function comporteIdentite(array $cles): bool
    {
        return collect(self::donneesListeParrain())->only($cles)->contains(fn (array $donnee) => $donnee[2]);
    }

    /**
     * Localité : zone d'intervention du parrain (régions entières ou provinces), plus les régions et provinces
     * ajoutées. Une province ajoutée limite sa région ajoutée à cette province. Sans aucun choix : aucun filtre.
     */
    private function dansLesLocalites(Builder $requete, ?Parrain $parrain, array $regions, array $provinces): Builder
    {
        $zones = $parrain?->zones ?? collect();
        $provincesAjoutees = Province::whereIn('id', $provinces)->get(['id', 'region_id']);
        $regionsEntieres = $zones->whereNull('province_id')->pluck('region_id')
            ->merge(array_diff(array_map('intval', $regions), $provincesAjoutees->pluck('region_id')->all()))
            ->unique()->values()->all();
        $provincesChoisies = $zones->whereNotNull('province_id')->pluck('province_id')
            ->merge($provincesAjoutees->pluck('id'))
            ->unique()->values()->all();

        if (! $regionsEntieres && ! $provincesChoisies) {
            return $requete;
        }

        return $requete->where(fn ($q) => $q->whereIn('region_id', $regionsEntieres)->orWhereIn('province_id', $provincesChoisies));
    }

    private function nomLocalite(string $type, ?int $id): ?string
    {
        if (! $id) {
            return null;
        }
        $modele = $type === 'region' ? Region::class : Province::class;

        return $this->nomsLocalites[$type][$id] ??= $modele::whereKey($id)->value('nom');
    }
}
