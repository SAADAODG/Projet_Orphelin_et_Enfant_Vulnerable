<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Plainte extends Model
{
    public const NOUVELLE = 'nouvelle';
    public const EN_COURS = 'en_cours';
    public const TRAITEE = 'traitee';

    public const STATUTS = [
        self::NOUVELLE => 'Nouvelle',
        self::EN_COURS => 'En cours',
        self::TRAITEE => 'Traitée',
    ];

    // Motifs d'expression des usagers sur la prise en charge des OEV
    public const OBJETS = [
        'contribution' => 'Contribution',
        'mecontentement' => 'Mécontentement',
        'reconnaissance' => 'Reconnaissance',
        'appreciation' => 'Appréciation',
    ];

    public const OBJETS_DESCRIPTIONS = [
        'contribution' => 'Une idée, une suggestion pour améliorer la prise en charge',
        'mecontentement' => "Un problème, une difficulté, quelque chose qui n'a pas fonctionné",
        'reconnaissance' => 'Remercier un agent, un service ou une action menée',
        'appreciation' => 'Donner votre avis sur le service reçu',
    ];

    public const OBJETS_ICONES = [
        'contribution' => 'bi-lightbulb',
        'mecontentement' => 'bi-emoji-frown',
        'reconnaissance' => 'bi-award',
        'appreciation' => 'bi-hand-thumbs-up',
    ];

    public const OBJETS_COULEURS = [
        'contribution' => 'info',
        'mecontentement' => 'danger',
        'reconnaissance' => 'success',
        'appreciation' => 'primary',
    ];

    protected $fillable = [
        'reference',
        'objet',
        'description',
        'region',
        'province',
        'localite',
        'recepisse_signalement',
        'anonyme',
        'nom',
        'telephone',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'anonyme' => 'boolean',
            'lu_at' => 'datetime',
        ];
    }

    /**
     * Génère une référence unique (ex : PL-2026-K7Q2ZP).
     */
    public static function genererReference(): string
    {
        do {
            $reference = 'PL-'.now()->year.'-'.Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function scopeNonLues(Builder $query): Builder
    {
        return $query->whereNull('lu_at');
    }

    public function getObjetLibelleAttribute(): string
    {
        return self::OBJETS[$this->objet] ?? $this->objet;
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getPeutEtreContacteAttribute(): bool
    {
        return filled($this->telephone) || filled($this->email);
    }
}
