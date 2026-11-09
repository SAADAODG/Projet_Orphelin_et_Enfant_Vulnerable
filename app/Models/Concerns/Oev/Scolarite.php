<?php

namespace App\Models\Concerns\Oev;

/**
 * Dossier OEV (App\Models\Oev) — Scolarité : classes par niveau, rang d’une classe, barème de la moyenne, enfant ni scolarisé ni en formation.
 * Les constantes (états, listes) restent dans le modèle : self:: les désigne.
 */
trait Scolarite
{
    /** L'enfant n'est ni scolarisé ni en formation professionnelle en cours : son souhait est demandé. */
    public static function sansScolariteNiFormation(?string $situationScolaire, mixed $formationProfessionnelle, ?string $formationEtat): bool
    {
        $enFormation = in_array($formationProfessionnelle, [true, 1, '1'], true) && $formationEtat !== 'achevee';

        return $situationScolaire !== null && $situationScolaire !== 'scolarise' && ! $enFormation;
    }

    /** Classes proposées pour un niveau d'étude. */
    public static function classesDuNiveau(?string $niveau): array
    {
        return self::CLASSES[$niveau] ?? [];
    }

    /** Toutes les classes, du préscolaire au supérieur (ex. pour la classe de l'année précédente). */
    public static function toutesLesClasses(): array
    {
        return array_merge(...array_values(self::CLASSES));
    }

    /** Niveau d'étude auquel appartient une classe. */
    public static function niveauDeLaClasse(?string $classe): ?string
    {
        foreach (self::CLASSES as $niveau => $classes) {
            if (array_key_exists((string) $classe, $classes)) {
                return $niveau;
            }
        }

        return null;
    }

    /**
     * Rang d'une classe dans le parcours scolaire (PS = 0 … Terminale, Licence…), pour comparer
     * l'année précédente et l'année en cours. Null si la classe est inconnue ou sans rang
     * (BTS / DUT, qui ne se place pas sur la suite Licence – Master).
     */
    public static function rangClasse(?string $classe): ?int
    {
        if ($classe === null || $classe === 'BTS') {
            return null;
        }
        $rang = array_search($classe, array_keys(self::toutesLesClasses()), true);

        return $rang === false ? null : $rang;
    }

    /** Barème de la moyenne : sur 10 au préscolaire et au primaire, sur 20 ensuite. */
    public static function baremeMoyenne(?string $classe): int
    {
        return in_array(self::niveauDeLaClasse($classe), self::NIVEAUX_NOTES_SUR_10, true) ? 10 : 20;
    }
}
