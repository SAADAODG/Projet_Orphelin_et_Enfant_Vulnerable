<?php

namespace App\Services\Parrainage;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Source de la sélection dont les listes sont dans la base : elles sont fournies comme requêtes, pour que
 * les indicateurs (pilotage) soient calculés par la base sans charger les listes en mémoire. Mêmes lignes
 * que listeDefinitive() et resultatsScolaires() de SourceSelection.
 */
interface SourceSelectionEnBase extends SourceSelection
{
    /**
     * Liste définitive des sessions données (sessions encore en cours exclues) : une ligne par bénéficiaire.
     *
     * @param  Collection<int, \App\Models\SessionParrainage>  $sessions
     * @return Builder colonnes oev_id, type_appui, montant_retenu
     */
    public function requeteListeDefinitive(Collection $sessions): Builder;

    /**
     * Résultats de fin d'année des parrainés.
     *
     * @return Builder colonnes oev_id, resultat
     */
    public function requeteResultatsScolaires(string $annee): Builder;
}
