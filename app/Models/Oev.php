<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'nom', 'prenom', 'sexe', 'date_naissance', 'statut', 'handicap', 'nature_handicap', 'systeme_educatif',
    'nom_tuteur', 'prenom_tuteur', 'contact_tuteur',
    'etablissement_precedent', 'moyenne_annuelle', 'appreciation',
    'etablissement_actuel', 'type_etablissement', 'classe', 'frais_scolarite',
    'region', 'province', 'commune',
    'nom_structure_rib', 'created_by',
    'numero_dossier', 'statut_dossier', 'soumis_at', 'soumis_par', 'verifie_at', 'verifie_par', 'motif_non_conformite', 'integre_at', 'integre_par',
    'motif_complement', 'complement_at', 'complement_par',
])]
class Oev extends Model
{
    use SoftDeletes;

    /*
     * Circuit du dossier : le DP constitue puis soumet ; le DR déclare le dossier conforme (validé)
     * ou non conforme (retour au DP) ; le niveau central intègre l'enfant, qui devient OEV et reçoit son code.
     * Le central peut aussi demander un complément : le dossier revient au DR, qui le complète
     * lui-même puis le valide à nouveau, ou le renvoie au DP (non conforme).
     */
    public const ETAT_BROUILLON = 'brouillon';
    public const ETAT_SOUMIS = 'soumis';
    public const ETAT_NON_CONFORME = 'non_conforme';
    public const ETAT_VALIDE = 'valide';
    public const ETAT_COMPLEMENT = 'complement';
    public const ETAT_INTEGRE = 'integre';

    public const ETATS = [
        self::ETAT_BROUILLON => 'En constitution',
        self::ETAT_SOUMIS => 'Soumis au DR',
        self::ETAT_NON_CONFORME => 'Non conforme',
        self::ETAT_VALIDE => 'Validé par le DR',
        self::ETAT_COMPLEMENT => 'Complément demandé',
        self::ETAT_INTEGRE => 'Intégré (OEV)',
    ];

    /** Couleur Bootstrap associée à chaque état. */
    public const COULEURS_ETATS = [
        self::ETAT_BROUILLON => 'secondary',
        self::ETAT_SOUMIS => 'info',
        self::ETAT_NON_CONFORME => 'danger',
        self::ETAT_VALIDE => 'primary',
        self::ETAT_COMPLEMENT => 'warning',
        self::ETAT_INTEGRE => 'success',
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

    public function estComplet(): bool
    {
        return $this->nombreDocuments() >= count(self::DOCUMENTS);
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

    public function nombreDocuments(): int
    {
        return $this->documents_count ?? ($this->relationLoaded('documents') ? $this->documents->count() : $this->documents()->count());
    }

    /** aucun | incomplet | complet */
    public function etatDossier(): string
    {
        $nombre = $this->nombreDocuments();

        return match (true) {
            $nombre === 0 => 'aucun',
            $nombre >= count(self::DOCUMENTS) => 'complet',
            default => 'incomplet',
        };
    }

    public function scopeDossierComplet(Builder $query): Builder
    {
        return $query->has('documents', '>=', count(self::DOCUMENTS));
    }

    public function scopeDossierIncomplet(Builder $query): Builder
    {
        return $query->has('documents', '>=', 1)->has('documents', '<', count(self::DOCUMENTS));
    }

    public function scopeSansDossier(Builder $query): Builder
    {
        return $query->doesntHave('documents');
    }
}
