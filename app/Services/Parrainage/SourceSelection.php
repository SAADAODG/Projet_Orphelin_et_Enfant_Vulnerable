<?php

namespace App\Services\Parrainage;

use App\Models\SessionParrainage;
use Illuminate\Support\Collection;

/**
 * Données fournies par les modules de Nakoulma (Sélection, Paiement, Suivi scolaire) et lues par
 * les modules Sessions, Parrains et Pilotage.
 *
 * Implémentée par SelectionParrainage (module Sélection, liée dans AppServiceProvider::register()).
 * Les établissements (RIB) n'y sont pas encore gérés : liste vide. Les résultats scolaires viennent du
 * module « Suivi des parrainés ».
 */
interface SourceSelection
{
    /** false tant que le module Sélection n'est pas branché : les écrans affichent « données non disponibles ». */
    public function disponible(): bool;

    /**
     * Nombre d'OEV éligibles par région pour la session, base de la proposition de quotas au prorata.
     *
     * @return array<int, int> region_id => nombre d'éligibles
     */
    public function eligiblesParRegion(SessionParrainage $session): array;

    /**
     * Montant total retenu sur la liste définitive (somme des montants retenus), en FCFA.
     *
     * @return int|null null si la liste définitive n'est pas disponible
     */
    public function montantEngage(SessionParrainage $session): ?int;

    /**
     * Liste définitive de la session : un élément par bénéficiaire.
     * type_appui : « scolaire » ou « formation_professionnelle » (utile pour une session « les_deux »).
     *
     * @return Collection<int, array{oev_id: int, type_appui: string, etablissement_id: int|null, frais_reels: int, montant_retenu: int}>
     */
    public function listeDefinitive(SessionParrainage $session): Collection;

    /**
     * Établissements payés (écoles, centres de formation) et leurs coordonnées bancaires, indexés par
     * identifiant. rib est null quand il n'est pas renseigné (signalé « RIB manquant »).
     *
     * @return Collection<int, array{id: int, nom: string, type: string|null, region_id: int|null, province_id: int|null, commune_id: int|null, banque: string|null, rib: string|null}>
     */
    public function etablissements(): Collection;

    /**
     * Natures d'appui que l'OEV reçoit de l'État l'année donnée (liste définitive d'une session),
     * pour le contrôle des doublons à la saisie d'un appui partenaire.
     *
     * @return array<int, string> codes de natures_appui : « scolaire » et / ou « formation_professionnelle »
     */
    public function naturesAppuiEtat(int $oevId, string $annee): array;

    /**
     * Résultats de fin d'année des parrainés (suivi scolaire).
     *
     * @return Collection<int, array{oev_id: int, annee: string, resultat: string}> resultat : admis, redouble ou abandon
     */
    public function resultatsScolaires(string $annee): Collection;
}
