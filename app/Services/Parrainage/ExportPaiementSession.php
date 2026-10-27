<?php

namespace App\Services\Parrainage;

use App\Models\BeneficiaireSession;
use App\Models\Commune;
use App\Models\Ecole;
use App\Models\Oev;
use App\Models\Province;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Support\Perimetre;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fichier Excel du paiement d'une session (onglet « Paiement ») : feuilles et colonnes choisies par
 * l'utilisateur, mêmes données que l'onglet (dont la réception des fonds).
 *  - « Par établissement » : sous-totaux par province et par région, total général ;
 *  - « Par région » : établissements, OEV, à verser, reçu, reste ;
 *  - nominatif : une feuille par région, rangée par province, établissement puis nom, sous-total par établissement.
 */
class ExportPaiementSession
{
    public const FEUILLES = [
        'etablissements' => 'Par établissement',
        'regions' => 'Par région',
        'nominatif' => 'Liste nominative (une feuille par région)',
    ];

    public const COLONNES = [
        'etablissements' => [
            'region' => 'Région', 'province' => 'Province', 'commune' => 'Commune', 'etablissement' => 'Établissement',
            'nature' => 'Nature', 'banque' => 'Banque', 'rib' => 'RIB', 'nombre_oev' => 'Nombre d’OEV',
            'montant' => 'Montant à verser (FCFA)', 'paiement' => 'Paiement', 'reference' => 'Référence du virement',
        ],
        'regions' => [
            'region' => 'Région', 'nombre_etablissements' => 'Établissements', 'etablissements_payes' => 'Établissements payés',
            'nombre_oev' => 'Nombre d’OEV', 'montant' => 'À verser (FCFA)', 'montant_paye' => 'Reçu (FCFA)', 'reste' => 'Reste (FCFA)',
        ],
        'nominatif' => [
            'province' => 'Province', 'etablissement' => 'Établissement', 'banque' => 'Banque', 'rib' => 'RIB',
            'code' => 'Code OEV', 'nom' => 'Nom', 'prenom' => 'Prénom(s)', 'sexe' => 'Sexe', 'date_naissance' => 'Date de naissance',
            'commune' => 'Commune de l’OEV', 'type_appui' => 'Type d’appui', 'classe' => 'Classe ou filière',
            'tuteur' => 'Parent / tuteur', 'telephone_tuteur' => 'Téléphone du tuteur',
            'frais_reels' => 'Frais réels (FCFA)', 'montant_retenu' => 'Montant retenu (FCFA)',
        ],
    ];

    /** Toujours présentes : elles portent les totaux. */
    public const OBLIGATOIRES = [
        'etablissements' => ['etablissement', 'nombre_oev', 'montant'],
        'regions' => ['region', 'montant'],
        'nominatif' => ['nom', 'montant_retenu'],
    ];

    public const PAR_DEFAUT = [
        'etablissements' => ['region', 'province', 'etablissement', 'banque', 'rib', 'nombre_oev', 'montant', 'paiement'],
        'regions' => ['region', 'nombre_etablissements', 'nombre_oev', 'montant', 'montant_paye', 'reste'],
        'nominatif' => ['etablissement', 'code', 'nom', 'prenom', 'sexe', 'classe', 'frais_reels', 'montant_retenu'],
    ];

    private const MONTANTS = ['montant', 'montant_paye', 'reste', 'frais_reels', 'montant_retenu'];

    public function __construct(private readonly PaiementsParrainage $paiements)
    {
    }

    /**
     * Colonnes choisies d'une feuille, dans l'ordre du catalogue, avec les obligatoires.
     *
     * @return array<string, string>
     */
    public static function colonnes(string $feuille, ?array $choix): array
    {
        $cles = array_merge($choix ?? self::PAR_DEFAUT[$feuille], self::OBLIGATOIRES[$feuille]);

        return array_intersect_key(self::COLONNES[$feuille], array_flip(array_map('strval', $cles)));
    }

    /** Nombre de lignes de la liste nominative (contrôle de taille avant de préparer le fichier). */
    public function nombreNominatif(SessionParrainage $session, Perimetre $zone, array $f): int
    {
        return (int) \Illuminate\Support\Facades\DB::query()->fromSub($this->paiements->parEtablissement($session, $zone, $f), 'x')->sum('x.nombre_oev');
    }

    /**
     * Feuilles du classeur.
     *
     * @param  array<string, array<string, string>>  $colonnes  feuille => colonnes choisies (feuilles retenues seulement)
     */
    public function feuilles(SessionParrainage $session, Perimetre $zone, array $f, array $colonnes): array
    {
        $regions = Region::pluck('nom', 'id');
        $provinces = Province::pluck('nom', 'id');
        $etablissements = $this->paiements->parEtablissement($session, $zone, $f)->get()
            ->map(fn ($e) => $this->ligneEtablissement($e, $regions, $provinces))
            ->sortBy([['region', 'asc'], ['province', 'asc'], ['etablissement', 'asc']])
            ->values();

        $feuilles = [];
        if (isset($colonnes['etablissements'])) {
            $feuilles[] = ['titre' => 'Par établissement', 'colonnes' => $colonnes['etablissements'], 'montants' => self::MONTANTS,
                'alerte' => 'rib_manquant', 'lignes' => $this->avecSousTotaux($etablissements)];
        }
        if (isset($colonnes['regions'])) {
            $feuilles[] = ['titre' => 'Par région', 'colonnes' => $colonnes['regions'], 'montants' => self::MONTANTS,
                'lignes' => $this->lignesRegions($etablissements)];
        }
        if (isset($colonnes['nominatif'])) {
            foreach ($this->nominatif($session, $zone, $f, $regions, $provinces)->groupBy('region') as $region => $oevs) {
                $feuilles[] = ['titre' => $region, 'colonnes' => $colonnes['nominatif'], 'montants' => self::MONTANTS,
                    'lignes' => $this->avecSousTotauxNominatif($oevs)];
            }
        }

        return $feuilles;
    }

    private function ligneEtablissement(object $e, Collection $regions, Collection $provinces): array
    {
        $rib = $e->rib === null || $e->rib === '' ? null : $e->rib;

        return [
            'niveau' => null,
            'region' => $regions[$e->region_id] ?? 'Région non renseignée',
            'province' => $provinces[$e->province_id] ?? '',
            'commune' => $e->commune,
            'etablissement' => $e->etablissement_id ? $e->nom : 'Établissement non renseigné',
            'nature' => $e->etablissement_id ? (Ecole::NATURES[$e->nature] ?? '') : '',
            'banque' => $e->banque,
            'rib' => $e->etablissement_id ? ($rib ?? 'RIB manquant') : '',
            'rib_manquant' => $e->etablissement_id && $rib === null,
            'nombre_oev' => (int) $e->nombre_oev,
            'montant' => (int) $e->montant,
            'paye' => $e->paiement_id !== null,
            'paiement' => $e->paiement_id ? 'Reçu le ' . Carbon::parse($e->recu_le)->format('d/m/Y') : ($e->etablissement_id ? 'À payer' : ''),
            'reference' => $e->reference,
        ];
    }

    /** Établissements avec un sous-total par province, par région, et le total général. */
    private function avecSousTotaux(Collection $etablissements): array
    {
        $total = fn (string $libelle, Collection $lignes, string $niveau) => [
            'niveau' => $niveau, 'libelle' => $libelle,
            'nombre_oev' => $lignes->sum('nombre_oev'), 'montant' => $lignes->sum('montant'),
        ];
        $lignes = [];
        foreach ($etablissements->groupBy('region') as $region => $parRegion) {
            foreach ($parRegion->groupBy('province') as $province => $parProvince) {
                array_push($lignes, ...$parProvince->all());
                $lignes[] = $total('Total province ' . ($province ?: 'non renseignée'), $parProvince, 'sous_total');
            }
            $lignes[] = $total("Total région {$region}", $parRegion, 'sous_total');
        }
        $lignes[] = $total('Total général', $etablissements, 'total');

        return $lignes;
    }

    private function lignesRegions(Collection $etablissements): array
    {
        $ligne = fn (string $libelle, Collection $e, ?string $niveau) => [
            'niveau' => $niveau,
            'libelle' => $libelle,
            'region' => $libelle,
            'nombre_etablissements' => $e->where('etablissement', '!=', 'Établissement non renseigné')->count(),
            'etablissements_payes' => $e->where('paye', true)->count(),
            'nombre_oev' => $e->sum('nombre_oev'),
            'montant' => $e->sum('montant'),
            'montant_paye' => $e->where('paye', true)->sum('montant'),
            'reste' => $e->where('paye', false)->sum('montant'),
        ];
        $lignes = $etablissements->groupBy('region')->map(fn ($e, $region) => $ligne($region, $e, null))->values()->all();
        $lignes[] = $ligne('Total', $etablissements, 'total');

        return $lignes;
    }

    /** OEV retenus, avec leur établissement, rangés par région, province, établissement puis nom. */
    private function nominatif(SessionParrainage $session, Perimetre $zone, array $f, Collection $regions, Collection $provinces): Collection
    {
        $etablissements = $this->paiements->parEtablissement($session, $zone, $f)->get()
            ->mapWithKeys(fn ($e) => [($e->etablissement_id ?? 0) . '-' . $e->region_id => $e]);
        $lignes = BeneficiaireSession::retenus()
            ->where('beneficiaires_session.session_parrainage_id', $session->id)
            ->whereHas('oev', fn ($q) => $zone->appliquer($q))
            ->join('oevs as o', 'o.id', '=', 'beneficiaires_session.oev_id')
            ->leftJoin('ecoles as e', 'e.id', '=', 'beneficiaires_session.etablissement_id')
            ->leftJoin('communes as c', 'c.id', '=', 'e.commune_id')
            ->leftJoin('provinces as pr', 'pr.id', '=', 'c.province_id')
            ->toBase()
            ->selectRaw('beneficiaires_session.etablissement_id, COALESCE(pr.region_id, beneficiaires_session.region_id) as region_id,
                beneficiaires_session.type_appui, beneficiaires_session.frais_reels, beneficiaires_session.montant_retenu,
                o.code, o.nom, o.prenom, o.sexe, o.date_naissance, o.commune_id, o.classe, o.formation_filiere, o.nom_tuteur, o.prenom_tuteur, o.contact_tuteur')
            ->get()
            // Mêmes filtres que les établissements (région, réception des fonds) : on garde les OEV de leurs lignes
            ->filter(fn ($b) => $etablissements->has(($b->etablissement_id ?? 0) . '-' . $b->region_id));
        $communes = Commune::whereIn('id', $lignes->pluck('commune_id')->filter()->unique()->values()->all())->pluck('nom', 'id');

        return $lignes->map(function ($b) use ($etablissements, $regions, $provinces, $communes) {
            $e = $etablissements[($b->etablissement_id ?? 0) . '-' . $b->region_id];
            $rib = $e->rib === null || $e->rib === '' ? null : $e->rib;

            return [
                'niveau' => null,
                'region' => $regions[$b->region_id] ?? 'Région non renseignée',
                'province' => $provinces[$e->province_id] ?? '',
                'etablissement' => $b->etablissement_id ? $e->nom : 'Établissement non renseigné',
                'banque' => $e->banque,
                'rib' => $b->etablissement_id ? ($rib ?? 'RIB manquant') : '',
                'code' => $b->code,
                'nom' => $b->nom,
                'prenom' => $b->prenom,
                'sexe' => Oev::SEXES[$b->sexe] ?? $b->sexe,
                'date_naissance' => $b->date_naissance ? Carbon::parse($b->date_naissance)->format('d/m/Y') : null,
                'commune' => $communes[$b->commune_id] ?? null,
                'type_appui' => SessionParrainage::TYPES_APPUI[$b->type_appui] ?? $b->type_appui,
                'classe' => $b->classe ?: ($b->formation_filiere ?: '—'),
                'tuteur' => trim($b->nom_tuteur . ' ' . $b->prenom_tuteur),
                'telephone_tuteur' => $b->contact_tuteur,
                'frais_reels' => $b->frais_reels === null ? null : (int) $b->frais_reels,
                'montant_retenu' => (int) $b->montant_retenu,
            ];
        })->sortBy([['region', 'asc'], ['province', 'asc'], ['etablissement', 'asc'], ['nom', 'asc'], ['prenom', 'asc']])->values();
    }

    /** OEV d'une région avec le sous-total de chaque établissement et le total de la région. */
    private function avecSousTotauxNominatif(Collection $oevs): array
    {
        $lignes = [];
        foreach ($oevs->groupBy(fn ($o) => $o['province'] . '|' . $o['etablissement']) as $parEtablissement) {
            array_push($lignes, ...$parEtablissement->all());
            $libelle = 'Total ' . $parEtablissement->first()['etablissement'] . ' (' . $parEtablissement->count() . ' OEV)';
            $lignes[] = ['niveau' => 'sous_total', 'libelle' => $libelle,
                'frais_reels' => $parEtablissement->sum('frais_reels'), 'montant_retenu' => $parEtablissement->sum('montant_retenu')];
        }
        $libelle = 'Total région (' . $oevs->count() . ' OEV)';
        $lignes[] = ['niveau' => 'total', 'libelle' => $libelle,
            'frais_reels' => $oevs->sum('frais_reels'), 'montant_retenu' => $oevs->sum('montant_retenu')];

        return $lignes;
    }
}
