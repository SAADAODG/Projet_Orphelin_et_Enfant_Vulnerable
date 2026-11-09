<?php

namespace App\Services\Oev;

use App\Models\Commune;
use App\Models\Ecole;
use App\Models\Oev;
use Closure;
use Illuminate\Http\Request;

/**
 * Cohérence entre les réponses du formulaire OEV : règles de validation Laravel (attribut, valeur, échec),
 * utilisées par ValidationOev.
 */
final class ReglesCoherenceOev
{
    /** Parent désigné comme tuteur alors qu'il est décédé, ou qu'on ne sait pas s'il est vivant. */
    private const TUTEUR_PARENT_REFUSE = [
        'mere' => [
            'decede' => 'La mère est déclarée décédée : elle ne peut pas être la tutrice de l’enfant.',
            'inconnu' => 'On ne sait pas si la mère est vivante : elle ne peut pas être indiquée comme tutrice.',
        ],
        'pere' => [
            'decede' => 'Le père est déclaré décédé : il ne peut pas être le tuteur de l’enfant.',
            'inconnu' => 'On ne sait pas si le père est vivant : il ne peut pas être indiqué comme tuteur.',
        ],
    ];

    /** École choisie parmi celles de la commune de l'enfant, ou « autre ». */
    public static function ecoleDeLaCommune(Request $request, bool $demandee): Closure
    {
        return function (string $attribut, $valeur, Closure $echec) use ($request, $demandee): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            if ($demandee && $valeur !== 'autre' && ! Ecole::whereKey($valeur)->where('commune_id', $request->input('commune_id'))->exists()) {
                $echec('Choisissez l’établissement dans la liste des écoles de la commune de l’enfant.');
            }
        };
    }

    /** Un parent tuteur doit être déclaré vivant. */
    public static function tuteurVivant(Request $request): Closure
    {
        return function (string $attribut, $valeur, Closure $echec) use ($request): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            foreach (Oev::parentsTuteurs($valeur) as $parent) {
                $etat = $request->input($parent === 'mere' ? 'mere_vivante' : 'pere_vivant');
                if ($etat !== 'oui') {
                    $echec(self::TUTEUR_PARENT_REFUSE[$parent][$etat === 'non' ? 'decede' : 'inconnu']);
                }
            }
        };
    }

    public static function communeDeProvenance(): Closure
    {
        return function (string $attribut, $valeur, Closure $echec): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            if ($valeur !== 'autre' && ! Commune::whereKey($valeur)->exists()) {
                $echec('Choisissez le lieu de provenance dans la liste.');
            }
        };
    }

    /** Moyenne : sur 10 au préscolaire et au primaire, sur 20 ensuite (selon la classe de l'année précédente). */
    public static function moyenneDansLeBareme(Request $request): Closure
    {
        $bareme = Oev::baremeMoyenne($request->input('classe_precedente'));

        return function (string $attribut, $valeur, Closure $echec) use ($bareme, $request): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            if (is_numeric($valeur) && $valeur > $bareme) {
                $classe = Oev::toutesLesClasses()[$request->input('classe_precedente')] ?? null;
                $echec("La moyenne doit être comprise entre 0 et {$bareme}" . ($classe ? " (notation sur {$bareme} en {$classe})." : '.'));
            }
        };
    }

    /** Appréciation cohérente avec la classe actuelle : un redoublant reste dans la même classe, un admis en change. */
    public static function appreciationCoherente(Request $request, bool $estScolarise): Closure
    {
        return function (string $attribut, $valeur, Closure $echec) use ($request, $estScolarise): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            $actuelle = $request->input('classe');
            $precedente = $request->input('classe_precedente');
            if (! $estScolarise || ! $actuelle || ! $precedente) {
                return;
            }
            // Une classe plus avancée l'an dernier qu'aujourd'hui est signalée sur la classe précédente
            $rangActuel = Oev::rangClasse($actuelle);
            $rangPrecedent = Oev::rangClasse($precedente);
            if ($rangActuel !== null && $rangPrecedent !== null && $rangPrecedent > $rangActuel) {
                return;
            }
            if ($valeur === 'redouble' && $actuelle !== $precedente) {
                $echec('L’enfant a redoublé : sa classe actuelle devrait être la même que l’année précédente.');
            }
            if ($valeur === 'admis' && $actuelle === $precedente) {
                $echec('L’enfant a été admis : sa classe actuelle ne peut pas être la même que l’année précédente.');
            }
        };
    }

    /** Formation : la durée déjà reçue ne peut pas dépasser la durée prévue. */
    public static function dureeRecueCoherente(Request $request): Closure
    {
        return function (string $attribut, $valeur, Closure $echec) use ($request): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            $totale = $request->input('formation_duree_mois');
            if (is_numeric($valeur) && is_numeric($totale) && (int) $valeur > (int) $totale) {
                $echec('La durée déjà reçue ne peut pas dépasser la durée de la formation.');
            }
        };
    }

    /** Le père peut être décédé pendant la grossesse (jusqu'à 9 mois avant la naissance), pas avant. */
    public static function decesPereCoherent(Request $request): Closure
    {
        return function (string $attribut, $valeur, Closure $echec) use ($request): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            $naissance = strtotime((string) $request->input('date_naissance'));
            if ($naissance && strtotime((string) $valeur) < strtotime('-9 months', $naissance)) {
                $echec('Le père ne peut pas être décédé plus de 9 mois avant la naissance de l’enfant.');
            }
        };
    }

    /** La classe en cours ne peut pas être en dessous de celle de l'année précédente (ex. 5e puis 6e). */
    public static function classeActuelleCoherente(Request $request): Closure
    {
        return function (string $attribut, $valeur, Closure $echec) use ($request): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            $rangActuel = Oev::rangClasse($valeur);
            $rangPrecedent = filled($request->input('etablissement_precedent')) ? Oev::rangClasse($request->input('classe_precedente')) : null;
            if ($rangActuel !== null && $rangPrecedent !== null && $rangActuel < $rangPrecedent) {
                $echec('La classe de l’année en cours ne peut pas être inférieure à celle de l’année précédente.');
            }
        };
    }

    /** Fonds propres de la famille : impossible quand les deux parents sont décédés. */
    public static function sourceRevenuCoherente(bool $deuxParentsDecedes): Closure
    {
        return function (string $attribut, $valeur, Closure $echec) use ($deuxParentsDecedes): void { // NOSONAR : $attribut imposé par Laravel (règle de validation appelée avec ses 3 paramètres par position)
            if ($deuxParentsDecedes && in_array($valeur, Oev::SOURCES_REVENU_PARENTS_VIVANTS, true)) {
                $echec('Les deux parents sont décédés : la source de revenu ne peut pas être « ' . Oev::SOURCES_REVENU[$valeur] . ' ».');
            }
        };
    }
}
