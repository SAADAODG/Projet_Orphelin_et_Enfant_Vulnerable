<?php

namespace App\Models;

use App\Models\Concerns\AppartientALocalite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Signalement extends Model
{
    use AppartientALocalite;

    public const EN_ATTENTE = 'en_attente';
    public const VALIDE = 'valide';
    public const REJETE = 'rejete';
    public const CLOTURE = 'cloture';

    // Cycle de vie : en attente -> validé (contact à faire) -> clôturé (contact fait, décision prise)
    //                en attente -> rejeté
    public const STATUTS = [
        self::EN_ATTENTE => 'En attente',
        self::VALIDE => 'Validé',
        self::CLOTURE => 'Clôturé',
        self::REJETE => 'Rejeté',
    ];

    public const STATUTS_COULEURS = [
        self::EN_ATTENTE => 'warning',
        self::VALIDE => 'primary',
        self::CLOTURE => 'success',
        self::REJETE => 'danger',
    ];

    public const PRISE_EN_CHARGE = 'prise_en_charge';
    public const NON_PRISE_EN_CHARGE = 'non_prise_en_charge';

    public const DECISIONS = [
        self::PRISE_EN_CHARGE => 'Prise en charge',
        self::NON_PRISE_EN_CHARGE => 'Non prise en charge',
    ];

    // Situations de vulnérabilité (plusieurs possibles, stockées séparées par des virgules)
    public const VULNERABILITES = [
        'orphelin' => 'Orphelin',
        'handicap' => 'En situation de handicap',
        'rue' => 'Abandonné ou en situation de rue',
        'violence' => "Victime de violence ou d'exploitation",
        'deplace' => 'Déplacé interne ou réfugié',
        'sante' => 'Malade chronique ou affecté par le VIH',
        'precarite' => 'Famille en grande précarité',
        'autre' => 'Autre situation',
    ];

    public const VULNERABILITES_DESCRIPTIONS = [
        'orphelin' => 'A perdu son père, sa mère ou les deux',
        'handicap' => 'Handicap physique, mental ou sensoriel',
        'rue' => 'Sans famille pour s\'occuper de lui, vit dans la rue',
        'violence' => 'Maltraitance, travail forcé, mariage précoce…',
        'deplace' => 'A dû quitter son foyer (insécurité, conflit…)',
        'sante' => 'Maladie de longue durée, VIH',
        'precarite' => 'La famille ne peut subvenir à ses besoins',
        'autre' => 'Une situation qui n\'est pas dans la liste',
    ];

    public const VULNERABILITES_ICONES = [
        'orphelin' => 'bi-heartbreak',
        'handicap' => 'bi-universal-access',
        'rue' => 'bi-signpost-split',
        'violence' => 'bi-shield-exclamation',
        'deplace' => 'bi-house-slash',
        'sante' => 'bi-heart-pulse',
        'precarite' => 'bi-basket',
        'autre' => 'bi-three-dots',
    ];

    public const LIENS = [
        'parent' => 'Parent',
        'tuteur' => 'Tuteur',
        'famille' => 'Membre de la famille',
        'voisin' => 'Voisin / Connaissance',
        'autre' => 'Autre',
    ];

    protected $fillable = [
        'recepisse',
        'enfant_nom',
        'enfant_prenom',
        'enfant_age',
        'vulnerabilite',
        'vulnerabilite_precision',
        'region_id',
        'province_id',
        'commune_id',
        'localite',
        'declarant_nom',
        'declarant_prenom',
        'declarant_telephone',
        'declarant_adresse',
        'declarant_profession',
        'declarant_lien',
        'declarant_lien_precision',
    ];

    protected function casts(): array
    {
        return [
            'traite_le' => 'datetime',
            'lu_at' => 'datetime',
            'date_visite' => 'date',
            'cloture_le' => 'datetime',
        ];
    }

    /**
     * Génère un numéro de récépissé unique (ex : OEV-2026-K7Q2ZP).
     */
    public static function genererRecepisse(): string
    {
        do {
            $recepisse = 'OEV-'.now()->year.'-'.Str::upper(Str::random(6));
        } while (static::where('recepisse', $recepisse)->exists());

        return $recepisse;
    }

    public function agentTraitement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function agentCloture(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par');
    }

    public function getDecisionLibelleAttribute(): ?string
    {
        return $this->decision ? (self::DECISIONS[$this->decision] ?? $this->decision) : null;
    }

    public function getStatutCouleurAttribute(): string
    {
        return self::STATUTS_COULEURS[$this->statut] ?? 'secondary';
    }

    public function getInitialesAttribute(): string
    {
        return Str::upper(Str::substr($this->enfant_prenom, 0, 1).Str::substr($this->enfant_nom, 0, 1));
    }

    public function scopeNonLus(Builder $query): Builder
    {
        return $query->whereNull('lu_at');
    }

    public function getEnfantNomCompletAttribute(): string
    {
        return $this->enfant_prenom.' '.$this->enfant_nom;
    }

    public function getDeclarantNomCompletAttribute(): string
    {
        return $this->declarant_prenom.' '.$this->declarant_nom;
    }

    public function getVulnerabiliteLibelleAttribute(): string
    {
        $libelles = collect(explode(',', (string) $this->vulnerabilite))
            ->filter()
            ->map(fn ($cle) => self::VULNERABILITES[$cle] ?? $cle)
            ->implode(', ');

        return $this->vulnerabilite_precision ? $libelles.' ('.$this->vulnerabilite_precision.')' : $libelles;
    }

    public function getLienLibelleAttribute(): string
    {
        $libelle = self::LIENS[$this->declarant_lien] ?? $this->declarant_lien;

        return $this->declarant_lien_precision ? $libelle.' ('.$this->declarant_lien_precision.')' : $libelle;
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }
}
