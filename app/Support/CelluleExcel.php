<?php

namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;

/** Lecture des cellules d'un fichier Excel importé (modèle d'import des appuis). */
final class CelluleExcel
{
    /** Date d'une cellule Excel : nombre de série Excel ou texte jj/mm/aaaa ; invalide → renvoyée telle quelle (rejetée). */
    public static function date(mixed $valeur): mixed
    {
        return match (true) {
            $valeur === null || $valeur === '' => null,
            is_numeric($valeur) => Carbon::instance(DateExcel::excelToDateTimeObject((float) $valeur))->toDateString(),
            default => self::dateDepuisTexte((string) $valeur) ?? $valeur,
        };
    }

    /** Texte jj/mm/aaaa → AAAA-MM-JJ ; null s'il n'est pas une date lisible. */
    private static function dateDepuisTexte(string $texte): ?string
    {
        try {
            return Carbon::createFromFormat('!d/m/Y', $texte)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
