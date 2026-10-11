<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suivi de fin d'année d'un OEV parrainé (retenu sur la liste définitive d'une session) :
 * résultat scolaire ou de formation, et recommandation pour un nouveau parrainage.
 */
class SuiviParrainage extends Model
{
    public const RESULTATS_SCOLAIRES = [
        'admis' => 'Admis en classe supérieure',
        'redouble' => 'Redouble',
        'abandon' => 'Abandon / exclusion',
    ];

    public const RESULTATS_FORMATION = [
        'poursuit' => 'Poursuit sa formation',
        'achevee' => 'Formation achevée',
        'abandon' => 'Abandon',
    ];

    /** Situation après une formation achevée. */
    public const INSERTIONS = [
        'emploi' => 'Emploi salarié',
        'auto_emploi' => 'Installé à son compte',
        'recherche' => 'En recherche d’emploi',
    ];

    /** Couleur Bootstrap de chaque résultat. */
    public const COULEURS = ['admis' => 'success', 'poursuit' => 'success', 'achevee' => 'primary', 'redouble' => 'warning', 'abandon' => 'danger'];

    protected $table = 'suivis_parrainage';

    /**
     * L'année suivante, indépendamment du résultat : un enfant admis peut quitter l'école pour une formation
     * professionnelle, un enfant qui a abandonné peut reprendre l'école.
     */
    public const ECOLE = 'ecole';
    public const FORMATION = 'formation';
    public const ARRET = 'arret';

    public const ORIENTATIONS = [
        self::ECOLE => 'Continue l’école',
        self::FORMATION => 'Part en formation professionnelle',
        self::ARRET => 'Arrête (ni école ni formation)',
    ];

    protected $fillable = [
        'beneficiaire_session_id', 'oev_id', 'annee', 'type_appui', 'resultat', 'moyenne', 'insertion', 'orientation', 'prochaine_annee', 'details_prochaine_annee',
        'a_reconduire', 'observation', 'fiche_avant', 'renseigne_par',
    ];

    /**
     * Classe de l'année suivante, calculée : la classe supérieure si l'OEV est admis, la même classe s'il
     * redouble ou reprend l'école après un abandon. Null si la classe actuelle est inconnue (ex. BTS).
     */
    public static function classeSuivante(?string $classe, string $resultat): ?string
    {
        $rang = Oev::rangClasse($classe);
        if ($rang === null) {
            return null;
        }

        return $resultat === 'admis' ? (array_keys(Oev::toutesLesClasses())[$rang + 1] ?? null) : $classe;
    }

    /**
     * Orientation d'un suivi de formation, déduite du résultat : poursuit → formation (même filière),
     * achevée → null (voir l'insertion), abandon → arrêt.
     */
    public static function orientationFormation(string $resultat): ?string
    {
        return match ($resultat) {
            'poursuit' => self::FORMATION,
            'abandon' => self::ARRET,
            default => null,
        };
    }

    /** L'année suivante en clair, ex. « Passe en 4e », « Redouble la 5e », « Formation professionnelle : Couture ». */
    public function libelleProchaineAnnee(): ?string
    {
        $classe = fn () => Oev::toutesLesClasses()[$this->prochaine_annee] ?? $this->prochaine_annee;

        return match ($this->orientation) {
            self::ECOLE => $this->prochaine_annee === null ? 'Continue l’école' : match ($this->resultat) {
                'admis' => 'Passe en ' . $classe(),
                'redouble' => 'Redouble la ' . $classe(),
                default => 'Reprend l’école en ' . $classe(),
            },
            self::FORMATION => 'Formation professionnelle' . ($this->prochaine_annee ? ' : ' . $this->prochaine_annee : ''),
            self::ARRET => $this->type_appui === SessionParrainage::TYPE_FORMATION ? 'Arrête la formation' : 'Arrête l’école',
            default => null,
        };
    }

    protected function casts(): array
    {
        return [
            'moyenne' => 'decimal:2',
            'a_reconduire' => 'boolean',
            'fiche_avant' => 'array',
            'details_prochaine_annee' => 'array',
        ];
    }

    public static function resultatsPour(string $typeAppui): array
    {
        return $typeAppui === SessionParrainage::TYPE_FORMATION ? self::RESULTATS_FORMATION : self::RESULTATS_SCOLAIRES;
    }

    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(BeneficiaireSession::class, 'beneficiaire_session_id');
    }

    public function oev(): BelongsTo
    {
        return $this->belongsTo(Oev::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renseigne_par')->withTrashed();
    }

    public function libelleResultat(): string
    {
        return self::resultatsPour($this->type_appui)[$this->resultat] ?? $this->resultat;
    }

    public function couleur(): string
    {
        return self::COULEURS[$this->resultat] ?? 'secondary';
    }

    /** Résumé court, ex. « 2026-2027 · Admis en classe supérieure (12,50/20) · à reconduire ». */
    public function resume(): string
    {
        $moyenne = $this->moyenne !== null
            ? ' (' . str_replace('.', ',', rtrim(rtrim((string) $this->moyenne, '0'), '.')) . ')'
            : '';

        $prochaine = $this->libelleProchaineAnnee() ? ' · l’an prochain : ' . mb_strtolower(mb_substr($this->libelleProchaineAnnee(), 0, 1)) . mb_substr($this->libelleProchaineAnnee(), 1) : '';

        return "{$this->annee} · {$this->libelleResultat()}{$moyenne}{$prochaine} · " . ($this->a_reconduire ? 'à reconduire' : 'non reconduit');
    }
}
