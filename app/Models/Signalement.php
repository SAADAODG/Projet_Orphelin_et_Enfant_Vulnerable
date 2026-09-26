<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Signalement extends Model
{
    public const EN_ATTENTE = 'en_attente';
    public const VALIDE = 'valide';
    public const REJETE = 'rejete';

    public const STATUTS = [
        self::EN_ATTENTE => 'En attente',
        self::VALIDE => 'Validé',
        self::REJETE => 'Rejeté',
    ];

    public const VULNERABILITES = [
        'orphelin' => 'Orphelin',
        'handicape' => 'Handicapé',
        'autre' => 'Autre',
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
        'region',
        'province',
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
        $libelle = self::VULNERABILITES[$this->vulnerabilite] ?? $this->vulnerabilite;

        return $this->vulnerabilite_precision ? $libelle.' ('.$this->vulnerabilite_precision.')' : $libelle;
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
