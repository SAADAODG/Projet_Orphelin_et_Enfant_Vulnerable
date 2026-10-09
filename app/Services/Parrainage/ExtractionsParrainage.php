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
     *   etablissements : une ligne par établissement (avec rib_manquant, RG-06), triées par région, province, nom ;
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
                'nom' => $oev->nom,
                'prenom' => $oev->prenom,
                'sexe' => Oev::SEXES[$oev->sexe] ?? $oev->sexe,
                'date_naissance' => $oev->date_naissance?->format('d/m/Y'),
                'classe' => $oev->classe ?: ($oev->formation_filiere ?: '—'),
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
     * @param  array{source: string, session: SessionParrainage|null, annee: string, nature: NatureAppui|null, zone_parrain: bool, region_id: int|null, province_id: int|null, commune_id: int|null, sexe: string}  $f
     *   source « attente » : liste d'attente de la session ; « eligibles » : OEV bénéficiaires sans appui de cette nature pour l'année.
     * @return Collection<int, Oev>
     */
    public function listeParrain(Parrain $parrain, User $utilisateur, array $f): Collection
    {
        $requete = Oev::beneficiaires()
            ->with(['commune'])
            ->dansLePerimetreDe($utilisateur)
            ->when($f['zone_parrain'], fn ($q) => $this->dansLaZoneDuParrain($q, $parrain))
            ->when($f['region_id'], fn ($q, $id) => $q->where('region_id', $id))
            ->when($f['province_id'], fn ($q, $id) => $q->where('province_id', $id))
            ->when($f['commune_id'], fn ($q, $id) => $q->where('commune_id', $id))
            ->when($f['sexe'], fn ($q, $sexe) => $q->where('sexe', $sexe));

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
     * Colonnes de la liste pour un parrain. Anonymisée : initiales, âge, sexe, commune, cycle ou filière,
     * besoin financier estimé, priorité. Identité complète (niveau central) : code, nom, prénom, date de naissance en plus.
     *
     * @return array<string, callable(Oev): mixed> libellé de colonne => valeur
     */
    public static function colonnesListeParrain(bool $identiteComplete): array
    {
        $identite = $identiteComplete ? [
            'Code OEV' => fn (Oev $o) => $o->code,
            'Nom' => fn (Oev $o) => $o->nom,
            'Prénom' => fn (Oev $o) => $o->prenom,
            'Date de naissance' => fn (Oev $o) => $o->date_naissance?->format('d/m/Y'),
        ] : [
            'Initiales' => fn (Oev $o) => $o->initiales(),
        ];

        return $identite + [
            'Âge' => fn (Oev $o) => $o->age(),
            'Sexe' => fn (Oev $o) => Oev::SEXES[$o->sexe] ?? $o->sexe,
            'Commune' => fn (Oev $o) => $o->commune?->nom,
            'Cycle ou filière' => fn (Oev $o) => $o->formation_professionnelle
                ? 'Formation professionnelle' . ($o->formation_filiere ? " ({$o->formation_filiere})" : '')
                : (Oev::NIVEAUX_ETUDE[$o->niveau_etude] ?? 'Non scolarisé ou non précisé'),
            'Besoin financier estimé (FCFA)' => fn (Oev $o) => (int) $o->besoin_estime,
            'Priorité' => fn (Oev $o) => Oev::NIVEAUX[$o->niveau_priorite] ?? 'Non précisée',
        ];
    }

    /** Zone du parrain : régions entières, ou provinces précises. Sans zone renseignée, aucun filtre. */
    private function dansLaZoneDuParrain(Builder $requete, Parrain $parrain): Builder
    {
        $zones = $parrain->zones;
        if ($zones->isEmpty()) {
            return $requete;
        }

        return $requete->where(fn ($q) => $q
            ->whereIn('region_id', $zones->whereNull('province_id')->pluck('region_id'))
            ->orWhereIn('province_id', $zones->whereNotNull('province_id')->pluck('province_id')));
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
