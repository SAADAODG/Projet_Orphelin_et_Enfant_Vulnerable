<?php

namespace App\Support;

/** Affichage des montants : FCFA sans décimales, séparateur de milliers (ex : 1 250 000 FCFA). */
final class Montant
{
    public static function fcfa(int|float|null $montant): string
    {
        return self::nombre($montant) . "\u{00A0}FCFA";
    }

    /** Saisie libre → chiffres : « 1 250 000 », « 1.250.000 FCFA » → « 1250000 » ; une saisie vide devient null. */
    public static function normaliserSaisie(mixed $valeur): mixed
    {
        if (! is_string($valeur)) {
            return $valeur;
        }
        $chiffres = preg_replace('/[\s.\x{00A0}\x{202F}]|fcfa/iu', '', $valeur);

        return $chiffres === '' ? null : $chiffres;
    }

    public static function nombre(int|float|null $montant): string
    {
        return number_format((int) round((float) $montant), 0, ',', "\u{00A0}");
    }
}
