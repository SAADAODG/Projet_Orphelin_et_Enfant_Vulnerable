<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Session de parrainage de l'État pour une année scolaire et un type d'appui. L'enveloppe (FCFA) est facultative
 * et peut être renseignée plus tard ; quand elle l'est, les OEV retenus ne la dépassent jamais.
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
        self::ETAT_VALIDEE => 'success',
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

    protected $table = 'sessions_parrainage';

    protected $fillable = [
        'description', 'annee', 'numero', 'type_appui', 'enveloppe', 'plafond_beneficiaire', 'estimation_scolarisation', 'estimation_formation',
        'date_ouverture', 'date_cloture', 'quotas_actifs', 'exclure_deja_appuyes',
        'etat', 'session_origine_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'enveloppe' => 'integer',
            'plafond_beneficiaire' => 'integer',
            'estimation_scolarisation' => 'integer',
            'estimation_formation' => 'integer',
            'date_ouverture' => 'date',
            'date_cloture' => 'date',
            'quotas_actifs' => 'boolean',
            'exclure_deja_appuyes' => 'boolean',
        ];
    }

    public function quotas(): HasMany
    {
        return $this->hasMany(QuotaRegional::class, 'session_parrainage_id');
    }

    /** OEV choisis pour la session (module « Sélection ») : retenus et liste d'attente. */
    public function beneficiaires(): HasMany
    {
        return $this->hasMany(BeneficiaireSession::class, 'session_parrainage_id');
    }

    /** Natures d'appui (codes de natures_appui) couvertes par la session. */
    public function naturesCouvertes(): array
    {
        return $this->type_appui === self::TYPE_LES_DEUX ? [self::TYPE_SCOLAIRE, self::TYPE_FORMATION] : [$this->type_appui];
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

    /** Numéro attribué à une nouvelle session de l'année : le suivant (1 pour la première). */
    public static function prochainNumero(string $annee): int
    {
        return (int) static::where('annee', $annee)->max('numero') + 1;
    }

    /** Ex : « 75 000 FCFA », ou « Sans plafond ». */
    public function libelleEnveloppe(): string
    {
        return $this->aUneEnveloppe() ? \App\Support\Montant::fcfa($this->enveloppe) : 'Enveloppe non renseignée';
    }

    public function libellePlafond(): string
    {
        return $this->plafond_beneficiaire === null ? 'Sans plafond' : \App\Support\Montant::fcfa($this->plafond_beneficiaire);
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

    /** Une enveloppe est fixée : les OEV retenus ne peuvent pas la dépasser. */
    public function aUneEnveloppe(): bool
    {
        return $this->enveloppe !== null;
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

    /**
     * Suppression : par un gestionnaire tant qu'aucun paiement n'est lancé ; ensuite par un administrateur seulement.
     */
    public function peutEtreSupprimeePar(User $utilisateur): bool
    {
        return $utilisateur->can('gérer sessions parrainage')
            && (in_array($this->etat, [self::ETAT_EN_COURS, self::ETAT_VALIDEE], true) || $utilisateur->supervise());
    }

    public function totalQuotas(): int
    {
        return (int) $this->quotas->sum('montant');
    }
}
