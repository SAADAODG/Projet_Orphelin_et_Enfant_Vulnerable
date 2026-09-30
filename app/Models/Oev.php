<?php

namespace App\Models;

use App\Models\Concerns\AppartientALocalite;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'nom', 'prenom', 'sexe', 'date_naissance', 'statut', 'handicap', 'nature_handicap', 'systeme_educatif',
    'nom_tuteur', 'prenom_tuteur', 'contact_tuteur',
    'etablissement_precedent', 'classe_precedente', 'moyenne_annuelle', 'appreciation', 'performance_scolaire', 'performance_difficultes',
    'etablissement_actuel', 'type_etablissement', 'classe', 'frais_scolarite',
    'region_id', 'province_id', 'commune_id', 'village_id',
    'nom_structure_rib', 'created_by',
    'numero_dossier', 'statut_dossier', 'soumis_at', 'soumis_par', 'verifie_at', 'verifie_par', 'motif_non_conformite', 'integre_at', 'integre_par',
    'motif_complement', 'complement_at', 'complement_par',
    'motif_rejet', 'rejete_niveau', 'rejete_at', 'rejete_par',
    // Fiche d'identification (champs OEV)
    'date_naissance_estimee', 'lieu_naissance', 'lieu_naissance_commune_id', 'nationalite', 'a_acte_naissance', 'numero_acte_naissance', 'groupe_population',
    'quartier', 'lieu_provenance',
    'mere_nom', 'mere_prenoms', 'mere_vivante', 'mere_date_deces', 'mere_deces_confirme',
    'pere_nom', 'pere_prenoms', 'pere_vivant', 'pere_date_deces', 'pere_deces_confirme',
    'lieu_de_vie', 'lieu_de_vie_precision', 'tuteur_sexe', 'tuteur_lien', 'tuteur_lien_precision', 'tuteur_a_cnib', 'tuteur_cnib', 'tuteur_pret_continuer', 'tuteur_raison_arret',
    'vulnerabilites', 'vulnerabilite_precision',
    'situation_scolaire', 'niveau_etude', 'raison_non_scolarisation', 'raison_non_scolarisation_precision',
    'formation_professionnelle', 'formation_etat', 'formation_filiere', 'formation_type_centre', 'formation_duree_mois', 'formation_duree_recue_mois',
    'types_handicap', 'maladie_chronique', 'maladie_nom', 'suivi_clinique',
    'source_revenu', 'niveau_revenu', 'logement',
    'date_identification', 'identifie_par', 'niveau_priorite',
])]
class Oev extends Model
{
    use AppartientALocalite, SoftDeletes;

    /*
     * Circuit du dossier : le DP constitue puis soumet ; le DR déclare le dossier conforme (validé)
     * ou non conforme (retour au DP) ; le niveau central intègre l'enfant, qui devient OEV et reçoit son code.
     * Le central peut aussi demander un complément : le dossier revient au DR, qui le complète
     * lui-même puis le valide à nouveau, ou le renvoie au DP (non conforme).
     * Le DR (dossier soumis ou en complément) et le central (dossier validé) peuvent rejeter définitivement le dossier.
     */
    public const ETAT_BROUILLON = 'brouillon';
    public const ETAT_SOUMIS = 'soumis';
    public const ETAT_NON_CONFORME = 'non_conforme';
    public const ETAT_VALIDE = 'valide';
    public const ETAT_COMPLEMENT = 'complement';
    public const ETAT_INTEGRE = 'integre';
    public const ETAT_REJETE = 'rejete';

    public const ETATS = [
        self::ETAT_BROUILLON => 'En constitution',
        self::ETAT_SOUMIS => 'Soumis au DR',
        self::ETAT_NON_CONFORME => 'Non conforme',
        self::ETAT_VALIDE => 'Validé par le DR',
        self::ETAT_COMPLEMENT => 'Complément demandé',
        self::ETAT_INTEGRE => 'Intégré (OEV)',
        self::ETAT_REJETE => 'Rejeté',
    ];

    /** Couleur Bootstrap associée à chaque état. */
    public const COULEURS_ETATS = [
        self::ETAT_BROUILLON => 'secondary',
        self::ETAT_SOUMIS => 'info',
        self::ETAT_NON_CONFORME => 'danger',
        self::ETAT_VALIDE => 'primary',
        self::ETAT_COMPLEMENT => 'warning',
        self::ETAT_INTEGRE => 'success',
        self::ETAT_REJETE => 'dark',
    ];

    public const SEXES = ['M' => 'Masculin', 'F' => 'Féminin'];

    public const STATUTS = [
        'orphelin_pere' => 'Orphelin de père',
        'orphelin_mere' => 'Orphelin de mère',
        'orphelin_double' => 'Orphelin de père et de mère',
        'vulnerable' => 'Enfant vulnérable',
    ];

    public const SYSTEMES_EDUCATIFS = [
        'classique' => 'Enseignement classique',
        'franco_arabe' => 'Franco-arabe',
        'technique' => 'Enseignement technique et professionnel',
        'autre' => 'Autre',
    ];

    public const APPRECIATIONS = ['admis' => 'Admis', 'redouble' => 'Redouble', 'exclu' => 'Exclu'];

    public const TYPES_ETABLISSEMENT = ['public' => 'Public', 'prive' => 'Privé'];

    /* ---------- Listes de la fiche d'identification (retenues pour les OEV) ---------- */

    public const OUI_NON = ['1' => 'Oui', '0' => 'Non'];

    /**
     * Lien du tuteur avec l'enfant. « parents » / « mere » / « pere » : seulement si le ou les parents
     * sont déclarés vivants ; leurs nom et prénoms sont alors repris de la partie « Parents ».
     */
    public const LIENS_TUTEUR = [
        'parents' => 'Les deux parents (père et mère)',
        'mere' => 'Mère',
        'pere' => 'Père',
        'grand_parent' => 'Grand-parent',
        'oncle_tante' => 'Oncle / tante',
        'frere_soeur' => 'Frère / sœur',
        'autre_parent' => 'Autre membre de la famille',
        'sans_lien' => 'Sans lien de parenté',
    ];

    /** Groupes de population pour lesquels le lieu de provenance est demandé. */
    public const GROUPES_MOBILES = ['pdi', 'migrant', 'rapatrie', 'refugie', 'demandeur_asile'];

    /** Classes par niveau d'étude (la classe doit correspondre au niveau). */
    public const CLASSES = [
        'prescolaire' => ['PS' => 'Petite section', 'MS' => 'Moyenne section', 'GS' => 'Grande section'],
        'primaire' => ['CP1' => 'CP1', 'CP2' => 'CP2', 'CE1' => 'CE1', 'CE2' => 'CE2', 'CM1' => 'CM1', 'CM2' => 'CM2'],
        'post_primaire_secondaire' => ['6e' => '6e', '5e' => '5e', '4e' => '4e', '3e' => '3e', '2nde' => '2nde', '1ère' => '1ère', 'Tle' => 'Terminale'],
        'superieur' => ['L1' => 'Licence 1', 'L2' => 'Licence 2', 'L3' => 'Licence 3', 'M1' => 'Master 1', 'M2' => 'Master 2', 'BTS' => 'BTS / DUT', 'Doctorat' => 'Doctorat'],
    ];

    public const PARENT_VIVANT = ['oui' => 'Oui', 'non' => 'Non (décédé)', 'ne_sait_pas' => 'Ne sait pas'];

    public const GROUPES_POPULATION = [
        'communaute' => 'Membre de la communauté',
        'pdi' => 'Personne déplacée interne (PDI)',
        'migrant' => 'Migrant',
        'rapatrie' => 'Rapatrié',
        'refugie' => 'Réfugié',
        'demandeur_asile' => 'Demandeur d’asile',
        'apatride' => 'Apatride',
        'autre' => 'Autre',
    ];

    public const LIEUX_DE_VIE = [
        'famille_biologique' => 'Famille biologique',
        'famille_elargie' => 'Famille élargie ou étendue',
        'famille_accueil' => 'Famille d’accueil',
        'structure_accueil' => 'Structure d’accueil / institution',
        'frere_soeur_adulte' => 'Frère ou sœur d’âge adulte',
        'vit_seul' => 'Vit seul',
        'enfant_chef_menage' => 'Sous la responsabilité d’un enfant chef de ménage',
        'site_deplaces' => 'Camp / site de réfugiés ou déplacés',
        'rue' => 'En rue',
        'autre' => 'Autre',
    ];

    public const VULNERABILITES = [
        'orphelin' => 'Orphelin',
        'separe' => 'Séparé',
        'non_accompagne' => 'Non accompagné',
        'abandon' => 'Abandon',
        'negligence' => 'Négligence',
        'sante_grave' => 'Problème de santé grave',
        'handicap' => 'Handicap',
        'sans_acte_naissance' => 'Absence d’acte de naissance',
        'precarite' => 'Précarité du ménage',
        'travail' => 'Travail des enfants',
        'enfant_marie' => 'Enfant marié',
        'grossesse' => 'Grossesse',
        'detresse' => 'Détresse psychosociale',
        'autre' => 'Autre',
    ];

    public const SITUATIONS_SCOLAIRES = [
        'scolarise' => 'Scolarisé',
        'non_scolarise' => 'Non scolarisé',
        'descolarise' => 'Déscolarisé',
        'non_formelle' => 'Éducation non formelle',
        'foyer_coranique' => 'Foyer coranique',
    ];

    public const NIVEAUX_ETUDE = [
        'prescolaire' => 'Préscolaire (maternelle)',
        'primaire' => 'Cycle primaire',
        'post_primaire_secondaire' => 'Cycle post-primaire et secondaire',
        'superieur' => 'Cycle supérieur',
    ];

    public const RAISONS_NON_SCOLARISATION = [
        'financieres' => 'Contraintes financières / matérielles',
        'infrastructures' => 'Absence d’infrastructures',
        'insecurite' => 'Insécurité / crise',
        'sante' => 'Problèmes de santé',
        'mariage' => 'Mariage',
        'grossesse' => 'Grossesse',
        'refus_famille' => 'Refus de la famille',
        'refus_enfant' => 'Refus de l’enfant',
        'autre' => 'Autre',
    ];

    /** Niveaux où les moyennes sont notées sur 10 (sur 20 ailleurs). */
    public const NIVEAUX_NOTES_SUR_10 = ['prescolaire', 'primaire'];

    public const PERFORMANCES_SCOLAIRES = [
        'bonnes' => 'Bonnes',
        'passables' => 'Passables',
        'faibles' => 'Faibles',
        'irregulieres' => 'Irrégulières',
        'difficultes' => 'Difficultés scolaires',
    ];

    public const ETATS_FORMATION = ['en_cours' => 'En cours', 'achevee' => 'Achevée'];

    public const TYPES_HANDICAP = [
        'auditif' => 'Déficience auditive',
        'visuel' => 'Déficience visuelle',
        'moteur' => 'Déficience motrice / mobilité',
        'mental' => 'Handicap mental',
    ];

    public const SOURCES_REVENU = [
        'fonds_propres' => 'Fonds propres de la famille',
        'aide_famille' => 'Aide d’autres membres de la famille',
        'ong_services' => 'Soutien d’ONG / services sociaux',
        'mendicite' => 'Pratique de la mendicité',
    ];

    public const NIVEAUX = ['faible' => 'Faible', 'moyen' => 'Moyen', 'eleve' => 'Élevé'];

    public const LOGEMENTS = [
        'proprietaire' => 'Propriétaire',
        'location' => 'Maison louée',
        'zone_non_lotie' => 'Zone non lotie',
        'site_deplaces' => 'Site / camp de réfugiés ou déplacés',
        'abri_precaire' => 'Abri précaire ou inadéquat',
        'sans_abri' => 'Sans-abri / vit dans la rue',
    ];

    public const IDENTIFIE_PAR = [
        'institution' => 'Une institution / école',
        'communaute' => 'La communauté',
        'famille' => 'La famille',
        'travailleur_social' => 'Le travailleur social',
        'services_sociaux' => 'Visite aux services sociaux',
        'particulier' => 'Un particulier',
    ];

    /**
     * Statut OEV déduit de la situation des parents : un parent décédé (« non ») fait de l'enfant un orphelin ;
     * sinon (vivants ou inconnus) l'enfant est considéré comme vulnérable.
     */
    public static function calculerStatut(?string $mereVivante, ?string $pereVivant): string
    {
        return match (true) {
            $mereVivante === 'non' && $pereVivant === 'non' => 'orphelin_double',
            $mereVivante === 'non' => 'orphelin_mere',
            $pereVivant === 'non' => 'orphelin_pere',
            default => 'vulnerable',
        };
    }

    /** Libellés d'un champ à choix multiples (tableau de codes), ex. vulnérabilités. */
    public function libelles(string $champ, array $liste): array
    {
        return collect((array) $this->{$champ})->map(fn ($code) => $liste[$code] ?? $code)->values()->all();
    }

    /** Pièces constituant le dossier d'un OEV. */
    public const DOCUMENTS = [
        'acte_naissance' => 'Acte de naissance',
        'certificat_scolarite' => 'Certificat de scolarité',
        'photo' => 'Photo de l’enfant',
        'cnib_tuteur' => 'CNIB du tuteur',
        'rib' => 'RIB de la structure',
    ];

    protected $table = 'oevs';

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'handicap' => 'boolean',
            'moyenne_annuelle' => 'decimal:2',
            'frais_scolarite' => 'integer',
            'deleted_at' => 'datetime',
            'soumis_at' => 'datetime',
            'verifie_at' => 'datetime',
            'integre_at' => 'datetime',
            'complement_at' => 'datetime',
            'rejete_at' => 'datetime',
            'date_naissance_estimee' => 'boolean',
            'a_acte_naissance' => 'boolean',
            'mere_date_deces' => 'date',
            'mere_deces_confirme' => 'boolean',
            'pere_date_deces' => 'date',
            'pere_deces_confirme' => 'boolean',
            'tuteur_pret_continuer' => 'boolean',
            'tuteur_a_cnib' => 'boolean',
            'vulnerabilites' => 'array',
            'formation_professionnelle' => 'boolean',
            'types_handicap' => 'array',
            'maladie_chronique' => 'boolean',
            'suivi_clinique' => 'boolean',
            'formation_duree_mois' => 'integer',
            'formation_duree_recue_mois' => 'integer',
            'date_identification' => 'date',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OevDocument::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function soumetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soumis_par');
    }

    public function verificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifie_par');
    }

    public function integrateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'integre_par');
    }

    public function demandeurComplement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complement_par');
    }

    public function auteurRejet(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejete_par');
    }

    /** Village ou secteur de résidence (4ᵉ niveau de localité, facultatif). */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /** Code OEV attribué à l'intégration, ex. OEV-2026-0001. */
    public static function genererCode(): string
    {
        return self::prochainNumero('code', 'OEV');
    }

    /** Numéro de dossier attribué à la constitution, ex. DOS-2026-0001. */
    public static function genererNumeroDossier(): string
    {
        return self::prochainNumero('numero_dossier', 'DOS');
    }

    private static function prochainNumero(string $colonne, string $prefixe): string
    {
        $prefixe .= '-' . now()->year . '-';
        $dernier = static::withTrashed()->where($colonne, 'like', $prefixe . '%')->orderByDesc($colonne)->value($colonne);
        $numero = $dernier ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    /** Identifiant affiché : le code OEV une fois intégré, sinon le numéro de dossier. */
    public function reference(): string
    {
        return $this->code ?: (string) $this->numero_dossier;
    }

    public function estIntegre(): bool
    {
        return $this->statut_dossier === self::ETAT_INTEGRE;
    }

    /** Le DP peut modifier, compléter, supprimer ou soumettre le dossier. */
    public function estModifiable(): bool
    {
        return in_array($this->statut_dossier, [self::ETAT_BROUILLON, self::ETAT_NON_CONFORME], true);
    }

    /** Le DR doit traiter le dossier : soumis par le DP, ou revenu du central avec une demande de complément. */
    public function attendDecisionDr(): bool
    {
        return in_array($this->statut_dossier, [self::ETAT_SOUMIS, self::ETAT_COMPLEMENT], true);
    }

    /**
     * Niveau habilité à rejeter le dossier à son étape actuelle, pour cet utilisateur :
     * « DR » (dossier soumis ou en complément), « Central » (dossier validé), sinon null.
     */
    public function niveauRejetPour(?User $utilisateur): ?string
    {
        return match (true) {
            $utilisateur === null => null,
            $this->attendDecisionDr() && $utilisateur->can('valider dossiers') => 'DR',
            $this->statut_dossier === self::ETAT_VALIDE && $utilisateur->can('intégrer OEV') => 'Central',
            default => null,
        };
    }

    /**
     * Qui peut modifier les informations et les pièces, et quand :
     * le DP pendant la constitution (ou après non-conformité), le DR quand le central demande un complément.
     */
    public function peutEtreModifiePar(?User $utilisateur): bool
    {
        if (! $utilisateur) {
            return false;
        }

        return ($this->estModifiable() && $utilisateur->can('constituer dossiers'))
            || ($this->statut_dossier === self::ETAT_COMPLEMENT && $utilisateur->can('valider dossiers'));
    }


    public function libelleEtat(): string
    {
        return self::ETATS[$this->statut_dossier] ?? (string) $this->statut_dossier;
    }

    public function couleurEtat(): string
    {
        return self::COULEURS_ETATS[$this->statut_dossier] ?? 'secondary';
    }

    public function scopeEtat(Builder $query, string ...$etats): Builder
    {
        return $query->whereIn('statut_dossier', $etats);
    }

    /**
     * États du circuit visibles par l'utilisateur (null = tous) : le DP voit tous les dossiers,
     * le DR à partir de leur soumission, le niveau central une fois validés par le DR ;
     * les autres rôles ne voient que les enfants intégrés. La supervision voit tout.
     */
    public static function etatsVisiblesPar(User $utilisateur): ?array
    {
        return match (true) {
            $utilisateur->supervise(), $utilisateur->can('constituer dossiers') => null,
            $utilisateur->can('valider dossiers') => [self::ETAT_SOUMIS, self::ETAT_NON_CONFORME, self::ETAT_VALIDE, self::ETAT_COMPLEMENT, self::ETAT_INTEGRE, self::ETAT_REJETE],
            $utilisateur->can('intégrer OEV') => [self::ETAT_VALIDE, self::ETAT_COMPLEMENT, self::ETAT_INTEGRE, self::ETAT_REJETE],
            default => [self::ETAT_INTEGRE],
        };
    }

    /** Dossiers visibles par l'utilisateur : ceux de sa zone, à l'étape où il intervient. */
    public function scopeVisiblesPar(Builder $query, User $utilisateur): Builder
    {
        $etats = self::etatsVisiblesPar($utilisateur);

        return $query->dansLePerimetreDe($utilisateur)->when($etats !== null, fn ($q) => $q->whereIn('statut_dossier', $etats));
    }

    public function estVisiblePar(User $utilisateur): bool
    {
        return static::query()->visiblesPar($utilisateur)->whereKey($this->getKey())->exists();
    }

    public function nomComplet(): string
    {
        return $this->nom . ' ' . $this->prenom;
    }

    /** Tailles maximales souhaitées par pièce (Ko), plafonnées par la configuration PHP du serveur. */
    public const TAILLE_MAX_PHOTO_KO = 2048;
    public const TAILLE_MAX_DOCUMENT_KO = 5120;

    public static function tailleMaxFichierKo(string $type): int
    {
        $souhaitee = $type === 'photo' ? self::TAILLE_MAX_PHOTO_KO : self::TAILLE_MAX_DOCUMENT_KO;

        return min($souhaitee, intdiv(self::octetsIni('upload_max_filesize'), 1024));
    }

    /** Taille totale maximale d'un envoi de formulaire (Ko), fixée par post_max_size. */
    public static function tailleMaxEnvoiKo(): int
    {
        return intdiv(self::octetsIni('post_max_size'), 1024);
    }

    /** Convertit une valeur php.ini ("2M", "8M", "1G"…) en octets ; 0 ou vide = illimité. */
    private static function octetsIni(string $cle): int
    {
        $valeur = trim((string) ini_get($cle));
        $nombre = (int) $valeur;
        if ($nombre <= 0) {
            return PHP_INT_MAX;
        }

        return match (strtolower(substr($valeur, -1))) {
            'g' => $nombre * 1024 ** 3,
            'm' => $nombre * 1024 ** 2,
            'k' => $nombre * 1024,
            default => $nombre,
        };
    }

    public const INDICATIF = '+226';

    /** Chiffres du numéro sans l'indicatif du Burkina Faso, ex. "+226 70 12 34 56" → "70123456". */
    public static function numeroLocal(?string $numero): string
    {
        $chiffres = preg_replace('/\D/', '', (string) $numero);

        return strlen($chiffres) === 11 && str_starts_with($chiffres, '226') ? substr($chiffres, 3) : $chiffres;
    }

    /** "70123456" → "+226 70 12 34 56" */
    public static function formaterTelephone(string $numeroLocal): string
    {
        return self::INDICATIF . ' ' . implode(' ', str_split($numeroLocal, 2));
    }

    /** Âge en années révolues, calculé à partir de la date de naissance. */
    public function age(): int
    {
        return (int) $this->date_naissance?->age;
    }

    /** Initiales pour l'avatar, ex. "OUEDRAOGO Awa" → "OA". */
    public function initiales(): string
    {
        return mb_strtoupper(mb_substr((string) $this->nom, 0, 1) . mb_substr((string) $this->prenom, 0, 1));
    }

    public function nomCompletTuteur(): string
    {
        return $this->nom_tuteur . ' ' . $this->prenom_tuteur;
    }

    /** Libellé d'une valeur codée, ex. $oev->libelle('statut', self::STATUTS). */
    public function libelle(string $champ, array $liste): string
    {
        return $liste[$this->{$champ}] ?? (string) $this->{$champ};
    }

    /* ---------- Pièces du dossier : seules celles qui s'appliquent à l'enfant sont exigées ---------- */

    /**
     * Pièces exigées pour cet enfant, d'après ses réponses :
     * pas d'acte de naissance → pas d'acte à charger ; non scolarisé → pas de certificat de scolarité ;
     * tuteur sans CNIB → pas de CNIB à charger. La photo et le RIB sont toujours demandés.
     *
     * @return array<string, string> type => libellé
     */
    public function piecesRequises(): array
    {
        return array_filter(self::DOCUMENTS, fn ($type) => self::pieceRequise($type, [
            'a_acte_naissance' => $this->a_acte_naissance,
            'situation_scolaire' => $this->situation_scolaire,
            'tuteur_a_cnib' => $this->tuteur_a_cnib,
        ]), ARRAY_FILTER_USE_KEY);
    }

    /** Règle d'exigence d'une pièce à partir des réponses (utilisée aussi à la validation). */
    public static function pieceRequise(string $type, array $reponses): bool
    {
        $faux = fn ($valeur) => $valeur === false || $valeur === 0 || $valeur === '0';

        return match ($type) {
            'acte_naissance' => ! $faux($reponses['a_acte_naissance'] ?? null),
            'certificat_scolarite' => ($reponses['situation_scolaire'] ?? null) === 'scolarise',
            'cnib_tuteur' => ! $faux($reponses['tuteur_a_cnib'] ?? null),
            default => true,
        };
    }

    /** Raison pour laquelle une pièce n'est pas demandée (affichée au DP). */
    public const RAISONS_PIECE_NON_REQUISE = [
        'acte_naissance' => 'l’enfant n’a pas d’acte de naissance',
        'certificat_scolarite' => 'l’enfant n’est pas scolarisé',
        'cnib_tuteur' => 'le tuteur n’a pas de CNIB',
    ];

    /** Types des pièces exigées déjà fournies. */
    public function piecesFournies(): array
    {
        $types = $this->relationLoaded('documents') ? $this->documents->pluck('type')->all() : $this->documents()->pluck('type')->all();

        return array_values(array_intersect(array_keys($this->piecesRequises()), $types));
    }

    public function nombreDocuments(): int
    {
        return count($this->piecesFournies());
    }

    public function estComplet(): bool
    {
        return count($this->piecesFournies()) >= count($this->piecesRequises());
    }

    /** Équivalent SQL de estComplet() : chaque pièce exigée par les réponses est jointe. */
    public function scopeDossierComplet(Builder $query): Builder
    {
        $fournie = fn (Builder $q, string $type) => $q->whereHas('documents', fn ($d) => $d->where('type', $type));

        return $query
            ->where(fn ($q) => $fournie($q, 'photo'))
            ->where(fn ($q) => $fournie($q, 'rib'))
            ->where(fn ($q) => $q->where('a_acte_naissance', false)->orWhere(fn ($q) => $fournie($q, 'acte_naissance')))
            ->where(fn ($q) => $q->whereNull('situation_scolaire')->orWhere('situation_scolaire', '!=', 'scolarise')
                ->orWhere(fn ($q) => $fournie($q, 'certificat_scolarite')))
            ->where(fn ($q) => $q->where('tuteur_a_cnib', false)->orWhere(fn ($q) => $fournie($q, 'cnib_tuteur')));
    }

    /* ---------- Cohérence des réponses ---------- */

    /**
     * Vulnérabilités déduites des réponses (cochées automatiquement, non modifiables à la main) :
     * orphelin si un parent est décédé, handicap, absence d'acte de naissance.
     */
    public static function vulnerabilitesAutomatiques(array $donnees): array
    {
        return array_keys(array_filter([
            'orphelin' => ($donnees['mere_vivante'] ?? null) === 'non' || ($donnees['pere_vivant'] ?? null) === 'non',
            'handicap' => (bool) ($donnees['handicap'] ?? false),
            'sans_acte_naissance' => isset($donnees['a_acte_naissance']) && ! $donnees['a_acte_naissance'],
        ]));
    }

    /** Classes proposées pour un niveau d'étude. */
    public static function classesDuNiveau(?string $niveau): array
    {
        return self::CLASSES[$niveau] ?? [];
    }

    /** Toutes les classes, du préscolaire au supérieur (ex. pour la classe de l'année précédente). */
    public static function toutesLesClasses(): array
    {
        return array_merge(...array_values(self::CLASSES));
    }

    /** Niveau d'étude auquel appartient une classe. */
    public static function niveauDeLaClasse(?string $classe): ?string
    {
        foreach (self::CLASSES as $niveau => $classes) {
            if (array_key_exists((string) $classe, $classes)) {
                return $niveau;
            }
        }

        return null;
    }

    /** Barème de la moyenne : sur 10 au préscolaire et au primaire, sur 20 ensuite. */
    public static function baremeMoyenne(?string $classe): int
    {
        return in_array(self::niveauDeLaClasse($classe), self::NIVEAUX_NOTES_SUR_10, true) ? 10 : 20;
    }

    /** Le parent ou les parents qui s'occupent de l'enfant (selon le lien choisi). */
    public static function parentsTuteurs(?string $lien): array
    {
        return match ($lien) {
            'mere' => ['mere'],
            'pere' => ['pere'],
            'parents' => ['pere', 'mere'],
            default => [],
        };
    }

    public function lieuNaissanceCommune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'lieu_naissance_commune_id');
    }

    /** Commune de naissance au Burkina, sinon le lieu saisi (autre lieu / étranger). */
    public function lieuDeNaissance(): ?string
    {
        return $this->lieuNaissanceCommune?->nom ?? $this->lieu_naissance;
    }
}
