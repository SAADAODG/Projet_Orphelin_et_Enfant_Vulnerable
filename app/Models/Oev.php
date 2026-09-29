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
    'etablissement_precedent', 'moyenne_annuelle', 'appreciation',
    'etablissement_actuel', 'type_etablissement', 'classe', 'frais_scolarite',
    'region_id', 'province_id', 'commune_id',
    'nom_structure_rib', 'created_by',
])]
class Oev extends Model
{
    use AppartientALocalite, SoftDeletes;

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

    public static function genererCode(): string
    {
        $prefixe = 'OEV-' . now()->year . '-';
        $dernier = static::withTrashed()->where('code', 'like', $prefixe . '%')->orderByDesc('code')->value('code');
        $numero = $dernier ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
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
