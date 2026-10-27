<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Paramètre du module OEV (clé => valeur), modifiable dans Paramètres > Âge des OEV.
 * Les valeurs sont lues une fois par requête (vidées à chaque modification).
 */
class ParametreOev extends Model
{
    protected $table = 'parametres_oev';

    protected $fillable = ['cle', 'valeur'];

    /** Âge jusqu'auquel un enfant a le statut d'OEV (il le perd à cet anniversaire). */
    public const AGE_LIMITE = 'age_limite';

    /** Même âge pour un enfant en situation de handicap : prise en charge plus longue. */
    public const AGE_LIMITE_HANDICAP = 'age_limite_handicap';

    public const DEFAUTS = [
        self::AGE_LIMITE => 18,
        self::AGE_LIMITE_HANDICAP => 25,
    ];

    /** Bornes acceptées à la saisie des âges. */
    public const AGE_MIN = 10;

    public const AGE_MAX = 30;

    public static function valeur(string $cle): mixed
    {
        return self::tous()->get($cle, self::DEFAUTS[$cle] ?? null);
    }

    /** Âge de sortie selon la situation de handicap. */
    public static function ageLimite(bool $handicap = false): int
    {
        return (int) self::valeur($handicap ? self::AGE_LIMITE_HANDICAP : self::AGE_LIMITE);
    }

    public static function enregistrer(string $cle, mixed $valeur): void
    {
        self::updateOrCreate(['cle' => $cle], ['valeur' => (string) $valeur]);
    }

    private static function tous(): Collection
    {
        if (! app()->bound('parametres-oev')) {
            app()->instance('parametres-oev', self::pluck('valeur', 'cle'));
        }

        return app('parametres-oev');
    }

    protected static function booted(): void
    {
        $vider = fn () => app()->forgetInstance('parametres-oev');
        static::saved($vider);
        static::deleted($vider);
    }
}
