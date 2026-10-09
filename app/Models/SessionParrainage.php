<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Session de parrainage : une enveloppe (FCFA) pour une année scolaire et un type d'appui.
 *
 * Cycle : en cours → validée (liste définitive arrêtée) → paiement en cours → clôturée.
 * Les transitions passent par ParrainageService::changerEtatSession().
 */
class SessionParrainage extends Model
{
    public const ETAT_EN_COURS = 'en_cours';
    public const ETAT_VALIDEE = 'validee';
    public const ETAT_PAIEMENT = 'paiement_en_cours';
    public const ETAT_CLOTUREE = 'cloturee';

    /** Dans l'ordre du cycle : l'état suivant est le suivant dans la liste. */
    public const ETATS = [
        self::ETAT_EN_COURS => 'En cours',
        self::ETAT_VALIDEE => 'Validée',
        self::ETAT_PAIEMENT => 'Paiement en cours',
        self::ETAT_CLOTUREE => 'Clôturée',
    ];

    public const COULEURS_ETATS = [
        self::ETAT_EN_COURS => 'info',
        self::ETAT_VALIDEE => 'primary',
        self::ETAT_PAIEMENT => 'warning',
        self::ETAT_CLOTUREE => 'secondary',
    ];

    public const TYPE_SCOLAIRE = 'scolaire';
    public const TYPE_FORMATION = 'formation_professionnelle';
    public const TYPE_LES_DEUX = 'les_deux';

    public const TYPES_APPUI = [
        self::TYPE_SCOLAIRE => 'Scolaire',
        self::TYPE_FORMATION => 'Formation professionnelle',
        self::TYPE_LES_DEUX => 'Scolaire et formation professionnelle',
    ];

    public const SOURCE_ETAT = 'etat';
    public const SOURCE_PARTENAIRE = 'partenaire';
    public const SOURCE_MIXTE = 'mixte';

    public const SOURCES_FINANCEMENT = [
        self::SOURCE_ETAT => 'État',
        self::SOURCE_PARTENAIRE => 'Partenaire',
        self::SOURCE_MIXTE => 'Mixte (État et partenaires)',
    ];

    /** Sources pour lesquelles au moins un parrain contributeur est exigé. */
    public const SOURCES_AVEC_PARRAINS = [self::SOURCE_PARTENAIRE, self::SOURCE_MIXTE];

    protected $table = 'sessions_parrainage';

    protected $fillable = [
        'description', 'annee', 'numero', 'type_appui', 'enveloppe', 'plafond_beneficiaire', 'source_financement',
        'date_ouverture', 'date_cloture', 'bloquer_depassement_enveloppe', 'quotas_actifs', 'exclure_deja_appuyes',
        'etat', 'session_origine_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'enveloppe' => 'integer',
            'plafond_beneficiaire' => 'integer',
            'date_ouverture' => 'date',
            'date_cloture' => 'date',
            'bloquer_depassement_enveloppe' => 'boolean',
            'quotas_actifs' => 'boolean',
            'exclure_deja_appuyes' => 'boolean',
        ];
    }

    public function quotas(): HasMany
    {
        return $this->hasMany(QuotaRegional::class, 'session_parrainage_id');
    }

    public function parrains(): BelongsToMany
    {
        return $this->belongsToMany(Parrain::class, 'contributions_session')->withPivot('montant')->withTimestamps();
    }

    public function sessionOrigine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'session_origine_id');
    }

    public function sessionsRattrapage(): HasMany
    {
        return $this->hasMany(self::class, 'session_origine_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Ex : « 2026-2027 · n°1 ». */
    public function reference(): string
    {
        return "{$this->annee} · n°{$this->numero}";
    }

    public function libelleEtat(): string
    {
        return self::ETATS[$this->etat] ?? $this->etat;
    }

    public function couleurEtat(): string
    {
        return self::COULEURS_ETATS[$this->etat] ?? 'secondary';
    }

    public function libelleTypeAppui(): string
    {
        return self::TYPES_APPUI[$this->type_appui] ?? $this->type_appui;
    }

    public function libelleSource(): string
    {
        return self::SOURCES_FINANCEMENT[$this->source_financement] ?? $this->source_financement;
    }

    public static function etatSuivant(string $etat): ?string
    {
        $etats = array_keys(self::ETATS);
        $position = array_search($etat, $etats, true);

        return $position === false ? null : ($etats[$position + 1] ?? null);
    }

    public static function etatPrecedent(string $etat): ?string
    {
        $etats = array_keys(self::ETATS);
        $position = array_search($etat, $etats, true);

        return $position ? $etats[$position - 1] : null;
    }

    /** Une session clôturée est en lecture seule. */
    public function estModifiable(): bool
    {
        return $this->etat !== self::ETAT_CLOTUREE;
    }

    /** Enveloppe, plafond, financement, options et quotas : modifiables tant que la session n'est pas validée. */
    public function parametresModifiables(): bool
    {
        return $this->etat === self::ETAT_EN_COURS;
    }

    public function avecParrains(): bool
    {
        return in_array($this->source_financement, self::SOURCES_AVEC_PARRAINS, true);
    }

    public function totalQuotas(): int
    {
        return (int) $this->quotas->sum('montant');
    }

    public function totalContributions(): int
    {
        return (int) $this->parrains->sum('pivot.montant');
    }
}
