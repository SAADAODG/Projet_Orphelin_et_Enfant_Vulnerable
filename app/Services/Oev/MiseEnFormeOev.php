<?php

namespace App\Services\Oev;

use App\Models\Ecole;
use App\Models\Oev;

/**
 * Mise en forme des données validées du formulaire OEV avant enregistrement (utilisée par ValidationOev) :
 * téléphones et statut, champs masqués effacés, écoles, lieux, tuteur, vulnérabilités.
 */
final class MiseEnFormeOev
{
    /** @param  bool  $sansActivite  enfant ni scolarisé ni en formation (souhait d'avenir conservé) */
    public static function appliquer(array $validated, bool $sansActivite): array
    {
        $validated = self::formater($validated);
        $validated = self::effacerChampsMasques($validated, $sansActivite);
        $validated = self::completerEcoles($validated);
        $validated = self::completerLieux($validated);
        $validated = self::completerTuteur($validated);

        return self::completerVulnerabilites($validated);
    }

    private static function formater(array $validated): array
    {
        $validated['contact_tuteur'] = Oev::formaterTelephone($validated['contact_tuteur']);
        $validated['gestionnaire_contact'] = filled($validated['gestionnaire_contact'] ?? null) ? Oev::formaterTelephone($validated['gestionnaire_contact']) : null;
        $validated['date_naissance_estimee'] = (bool) ($validated['date_naissance_estimee'] ?? false);
        // Liste des villages vide ou désactivée : non envoyée, l'ancien village ne doit pas rester
        $validated['village_id'] ??= null;
        $validated['statut'] = Oev::calculerStatut($validated['mere_vivante'], $validated['pere_vivant']);

        return $validated;
    }

    /** Vide les champs quand la condition est remplie. */
    private static function effacer(array &$validated, bool $condition, array $champs): void
    {
        if ($condition) {
            foreach ($champs as $champ) {
                $validated[$champ] = null;
            }
        }
    }

    /** Les informations masquées par une réponse ne sont pas conservées (ex. date de décès d'une mère vivante). */
    private static function effacerChampsMasques(array $v, bool $sansActivite): array
    {
        self::effacer($v, $v['mere_vivante'] !== 'non', ['mere_date_deces', 'mere_deces_confirme']);
        self::effacer($v, $v['pere_vivant'] !== 'non', ['pere_date_deces', 'pere_deces_confirme']);
        self::effacer($v, ($v['lieu_de_vie'] ?? null) !== 'autre', ['lieu_de_vie_precision']);
        self::effacer($v, ! $v['handicap'], ['types_handicap', 'nature_handicap']);
        self::effacer($v, ! in_array($v['groupe_population'] ?? null, Oev::GROUPES_MOBILES, true), ['lieu_provenance', 'lieu_provenance_commune_id']);
        self::effacer($v, ! $v['tuteur_a_cnib'], ['tuteur_cnib']);
        self::effacer($v, $v['tuteur_lien'] !== 'autre_parent', ['tuteur_lien_precision']);
        self::effacer($v, (bool) $v['tuteur_pret_continuer'], ['tuteur_raison_arret']);
        self::effacer($v, ! $v['a_acte_naissance'], ['numero_acte_naissance']);
        self::effacer($v, ! ($v['maladie_chronique'] ?? false), ['maladie_nom', 'suivi_clinique']);
        self::effacer($v, $v['situation_scolaire'] !== 'scolarise', ['systeme_educatif', 'etablissement_actuel', 'rib_etablissement', 'type_etablissement', 'classe', 'frais_scolarite', 'performance_scolaire', 'performance_difficultes']);
        self::effacer($v, ($v['performance_scolaire'] ?? null) !== 'difficultes', ['performance_difficultes']);
        self::effacer($v, ! in_array($v['situation_scolaire'], ['scolarise', 'descolarise'], true), ['niveau_etude']);
        self::effacer($v, $v['situation_scolaire'] === 'non_scolarise', ['etablissement_precedent', 'classe_precedente', 'moyenne_annuelle', 'appreciation']);
        self::effacer($v, blank($v['etablissement_precedent'] ?? null), ['classe_precedente', 'moyenne_annuelle', 'appreciation']);
        self::effacer($v, ! in_array($v['situation_scolaire'], ['non_scolarise', 'descolarise'], true), ['raison_non_scolarisation', 'raison_non_scolarisation_precision']);
        self::effacer($v, ($v['raison_non_scolarisation'] ?? null) !== 'autre', ['raison_non_scolarisation_precision']);
        self::effacer($v, ! ($v['formation_professionnelle'] ?? false), ['formation_etat', 'formation_filiere', 'formation_type_centre', 'centre_formation_id', 'formation_duree_mois', 'formation_duree_recue_mois']);
        // Souhait seulement pour un enfant ni scolarisé ni en formation ; la formation souhaitée seulement s'il choisit la formation
        self::effacer($v, ! $sansActivite, ['souhait_avenir', 'souhait_avenir_precision']);
        self::effacer($v, ($v['souhait_avenir'] ?? null) !== 'formation', ['souhait_avenir_precision']);
        // Formation achevée : toute la durée a été reçue
        if (($v['formation_etat'] ?? null) === 'achevee') {
            $v['formation_duree_recue_mois'] = $v['formation_duree_mois'];
        }

        return $v;
    }

    private static function completerEcoles(array $v): array
    {
        // École de la liste : son nom est repris comme établissement fréquenté ; sinon établissement saisi, sans école rattachée
        if ($v['situation_scolaire'] === 'scolarise' && filled($v['ecole_id'] ?? null) && $v['ecole_id'] !== 'autre') {
            $v['etablissement_actuel'] = Ecole::findOrFail($v['ecole_id'])->nom;
        } else {
            $v['ecole_id'] = null;
        }
        // École précédente de la liste (son nom a été repris avant la validation) ; sinon établissement saisi ou aucun
        if (filled($v['etablissement_precedent'] ?? null) && filled($v['ecole_precedente_id'] ?? null) && $v['ecole_precedente_id'] !== 'autre') {
            $v['ecole_precedente_id'] = (int) $v['ecole_precedente_id'];
        } else {
            $v['ecole_precedente_id'] = null;
        }

        return $v;
    }

    private static function completerLieux(array $v): array
    {
        // Lieu de naissance saisi librement : l'ancien rattachement à une commune est abandonné
        $v['lieu_naissance_commune_id'] = null;
        // Lieu de provenance : commune du référentiel, ou lieu saisi quand il n'y figure pas
        if (($v['lieu_provenance_commune_id'] ?? null) === 'autre') {
            $v['lieu_provenance_commune_id'] = null;
        } else {
            $v['lieu_provenance'] = null;
        }

        return $v;
    }

    /** Tuteur = parent(s) : nom et prénoms repris de la partie « Parents », sexe déduit. */
    private static function completerTuteur(array $v): array
    {
        $parents = Oev::parentsTuteurs($v['tuteur_lien']);
        if ($parents !== []) {
            $v['nom_tuteur'] = collect($parents)->map(fn ($p) => $v["{$p}_nom"])->unique()->implode(' / ');
            $v['prenom_tuteur'] = collect($parents)->map(fn ($p) => $v["{$p}_prenoms"])->implode(' et ');
        }
        $v['tuteur_sexe'] = match ($v['tuteur_lien']) {
            'mere' => 'F',
            'pere' => 'M',
            'parents' => null,
            default => $v['tuteur_sexe'] ?? null,
        };

        return $v;
    }

    /** Vulnérabilités : celles déduites des réponses sont ajoutées d'office ; « grossesse » ne concerne que les filles. */
    private static function completerVulnerabilites(array $v): array
    {
        $vulnerabilites = array_diff((array) ($v['vulnerabilites'] ?? []), ['orphelin', 'handicap', 'sans_acte_naissance']);
        if ($v['sexe'] !== 'F') {
            $vulnerabilites = array_diff($vulnerabilites, ['grossesse']);
        }
        $vulnerabilites = array_values(array_unique(array_merge(Oev::vulnerabilitesAutomatiques($v), $vulnerabilites)));
        $v['vulnerabilites'] = $vulnerabilites ?: null;
        self::effacer($v, ! in_array('autre', $vulnerabilites, true), ['vulnerabilite_precision']);

        return $v;
    }
}
