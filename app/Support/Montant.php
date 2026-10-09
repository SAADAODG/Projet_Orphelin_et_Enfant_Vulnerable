<?php

namespace App\Support;

/** Affichage des montants : FCFA sans décimales, séparateur de milliers (ex : 1 250 000 FCFA). */
final class Montant
{
    public static function fcfa(int|float|null $montant): string
    {
        return self::nombre($montant) . "\u{00A0}FCFA";
    }

    public static function nombre(int|float|null $montant): string
    {
        return number_format((int) round((float) $montant), 0, ',', "\u{00A0}");
    }
}
