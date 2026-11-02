<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Ecole;
use App\Models\Oev;
use App\Models\User;
use App\Services\Oev\ValidationOev;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\ValidationOevAvant;
use Tests\TestCase;

/**
 * Le service ValidationOev donne exactement les mêmes résultats que l'ancienne méthode OevController::valider() :
 * mêmes données validées (valeurs, types et ordre des clés) ou mêmes erreurs (champs, messages et ordre).
 */
class ValidationOevEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    private Commune $ouagadougou;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);
        $this->ouagadougou = Commune::where('nom', 'Ouagadougou')->firstOrFail();
    }

    private function donneesOev(array $surcharge = []): array
    {
        return array_merge([
            'nom' => 'OUEDRAOGO',
            'prenom' => 'Awa',
            'sexe' => 'F',
            'date_naissance' => now()->subYears(11)->subMonths(2)->toDateString(),
            'mere_vivante' => 'oui',
            'pere_vivant' => 'non',
            'a_acte_naissance' => '1',
            'numero_acte_naissance' => 'AN-2015-0457',
            'tuteur_lien' => 'oncle_tante',
            'tuteur_sexe' => 'M',
            'tuteur_a_cnib' => '1',
            'tuteur_cnib' => 'B1234567',
            'tuteur_pret_continuer' => '1',
            'handicap' => '0',
            'situation_scolaire' => 'scolarise',
            'niveau_etude' => 'post_primaire_secondaire',
            'systeme_educatif' => 'classique',
            'nom_tuteur' => 'SAWADOGO',
            'prenom_tuteur' => 'Issa',
            'contact_tuteur' => '+226 70 00 00 00',
            'etablissement_precedent' => 'École A de Koulouba',
            'classe_precedente' => 'CM2',
            'moyenne_annuelle' => '7.25',
            'appreciation' => 'admis',
            'performance_scolaire' => 'bonnes',
            'etablissement_actuel' => 'Lycée Philippe Zinda Kaboré',
            'type_etablissement' => 'public',
            'classe' => '6e',
            'frais_scolarite' => '25000',
            'region_id' => (string) $this->ouagadougou->province->region_id,
            'province_id' => (string) $this->ouagadougou->province_id,
            'commune_id' => (string) $this->ouagadougou->id,
        ], $surcharge);
    }

    /** Résultat d'une implémentation : données validées, ou erreurs de validation. */
    private function resultat(callable $valider, User $utilisateur, array $donnees, array $fichiers): array
    {
        $request = Request::create('/oevs', 'POST', $donnees, [], $fichiers);
        $request->setUserResolver(fn () => $utilisateur);

        try {
            return ['donnees' => $valider($request)];
        } catch (ValidationException $e) {
            return ['erreurs' => $e->errors()];
        }
    }

    public function test_le_service_valide_comme_l_ancienne_methode(): void
    {
        $province = $this->ouagadougou->province;
        $autreCommune = Commune::where('province_id', '!=', $province->id)->firstOrFail();
        $dp = User::factory()->create(['region_id' => $province->region_id, 'province_id' => $province->id])->assignRole('DP');
        $dr = User::factory()->create(['region_id' => $province->region_id])->assignRole('DR');
        $sansZone = User::factory()->create()->assignRole('DP');
        $autreDp = User::factory()->create(['region_id' => $autreCommune->province->region_id, 'province_id' => $autreCommune->province_id])->assignRole('DP');

        $ecole = Ecole::create(['nature' => Ecole::NATURE_ECOLE, 'commune_id' => $this->ouagadougou->id, 'nom' => 'École Wemtenga']);
        $ecolePrecedente = Ecole::create(['nature' => Ecole::NATURE_ECOLE, 'commune_id' => $this->ouagadougou->id, 'nom' => 'École B de Koulouba']);
        $ecoleAilleurs = Ecole::create(['nature' => Ecole::NATURE_ECOLE, 'commune_id' => $autreCommune->id, 'nom' => 'École de la province voisine']);
        $centre = Ecole::create(['nature' => Ecole::NATURE_CENTRE, 'commune_id' => $this->ouagadougou->id, 'nom' => 'Centre de couture']);

        $nonScolarise = ['situation_scolaire' => 'non_scolarise', 'niveau_etude' => '', 'systeme_educatif' => '', 'etablissement_actuel' => '', 'type_etablissement' => '',
            'classe' => '', 'performance_scolaire' => '', 'etablissement_precedent' => '', 'classe_precedente' => '', 'moyenne_annuelle' => '', 'appreciation' => ''];
        $descolarise = ['situation_scolaire' => 'descolarise', 'systeme_educatif' => '', 'etablissement_actuel' => '', 'type_etablissement' => '', 'classe' => '', 'performance_scolaire' => ''];
        $formation = ['formation_professionnelle' => '1', 'formation_etat' => 'en_cours', 'formation_filiere' => 'Couture', 'formation_type_centre' => 'prive', 'formation_duree_mois' => '24'];
        $parentsVivants = ['pere_vivant' => 'oui', 'mere_nom' => 'SAWADOGO', 'mere_prenoms' => 'Mariam', 'pere_nom' => 'OUEDRAOGO', 'pere_prenoms' => 'Paul'];

        $scenarios = [
            'dossier de base' => [],
            'téléphones et gestionnaire' => ['contact_tuteur' => '70 11 22 33', 'gestionnaire_nom' => 'KABORE', 'gestionnaire_fonction' => 'Action sociale', 'gestionnaire_contact' => '+226 71 22 33 44'],
            'téléphone du gestionnaire invalide' => ['gestionnaire_contact' => '123'],
            'téléphone du tuteur manquant' => ['contact_tuteur' => ''],
            'date de naissance estimée' => ['date_naissance_estimee' => '1'],
            'naissance dans le futur' => ['date_naissance' => now()->addDay()->toDateString()],
            'trop âgé' => ['date_naissance' => now()->subYears(17)->toDateString()],
            'trop âgé, handicap' => ['date_naissance' => now()->subYears(21)->toDateString(), 'handicap' => '1', 'types_handicap' => ['moteur']],
            'handicap sans type' => ['handicap' => '1'],
            'handicap complet' => ['handicap' => '1', 'types_handicap' => ['moteur', 'visuel'], 'nature_handicap' => 'Fauteuil'],
            'type de handicap sans handicap' => ['types_handicap' => ['moteur'], 'nature_handicap' => 'Effacée'],
            'sans acte, numéro saisi' => ['a_acte_naissance' => '0', 'numero_acte_naissance' => '123'],
            'acte sans numéro' => ['numero_acte_naissance' => ''],
            'sans CNIB' => ['tuteur_a_cnib' => '0'],
            'tuteur ne peut pas continuer, sans raison' => ['tuteur_pret_continuer' => '0'],
            'tuteur ne peut pas continuer, avec raison' => ['tuteur_pret_continuer' => '0', 'tuteur_raison_arret' => 'Manque de moyens'],
            'maladie sans nom' => ['maladie_chronique' => '1'],
            'maladie complète' => ['maladie_chronique' => '1', 'maladie_nom' => 'Drépanocytose', 'suivi_clinique' => '1'],
            'mère tutrice' => ['tuteur_lien' => 'mere', 'mere_nom' => 'SAWADOGO', 'mere_prenoms' => 'Mariam', 'nom_tuteur' => '', 'prenom_tuteur' => '', 'tuteur_sexe' => 'M'],
            'mère tutrice sans nom' => ['tuteur_lien' => 'mere', 'nom_tuteur' => '', 'prenom_tuteur' => '', 'tuteur_sexe' => ''],
            'père décédé tuteur' => ['tuteur_lien' => 'pere', 'pere_nom' => 'OUEDRAOGO', 'pere_prenoms' => 'Paul'],
            'parents tuteurs, mère inconnue' => ['tuteur_lien' => 'parents', 'mere_vivante' => 'ne_sait_pas'] + $parentsVivants,
            'parents tuteurs vivants' => ['tuteur_lien' => 'parents', 'tuteur_sexe' => 'M'] + $parentsVivants,
            'autre parent sans précision' => ['tuteur_lien' => 'autre_parent'],
            'autre parent précisé' => ['tuteur_lien' => 'autre_parent', 'tuteur_lien_precision' => 'Grand-mère'],
            'précision du lien effacée' => ['tuteur_lien_precision' => 'Effacée'],
            'mère décédée avant la naissance' => ['mere_vivante' => 'non', 'mere_date_deces' => now()->subYears(13)->toDateString()],
            'mère décédée après la naissance' => ['mere_vivante' => 'non', 'mere_date_deces' => now()->subYears(3)->toDateString(), 'mere_deces_confirme' => '1', 'source_revenu' => 'aide_famille'],
            'deux parents décédés, fonds propres' => ['mere_vivante' => 'non', 'source_revenu' => 'fonds_propres'],
            'date de décès d’une mère vivante' => ['mere_date_deces' => now()->subYears(3)->toDateString(), 'mere_deces_confirme' => '1'],
            'père décédé 2 ans avant la naissance' => ['pere_date_deces' => now()->subYears(13)->toDateString()],
            'père décédé pendant la grossesse' => ['pere_date_deces' => now()->subYears(11)->subMonths(6)->toDateString(), 'pere_deces_confirme' => '1'],
            'ménage et identification' => ['niveau_revenu' => 'faible', 'logement' => array_key_first(Oev::LOGEMENTS), 'niveau_priorite' => 'eleve',
                'identifie_par' => array_key_first(Oev::IDENTIFIE_PAR), 'date_identification' => now()->subYear()->toDateString()],
            'identification avant la naissance' => ['date_identification' => now()->subYears(12)->toDateString()],
            'valeurs de listes inconnues' => ['niveau_revenu' => 'xx', 'logement' => 'xx', 'sexe' => 'X', 'mere_vivante' => 'peut-etre', 'situation_scolaire' => 'xx'],
            'déplacé, lieu hors liste manquant' => ['groupe_population' => 'pdi', 'lieu_provenance_commune_id' => 'autre'],
            'déplacé, lieu hors liste' => ['groupe_population' => 'pdi', 'lieu_provenance_commune_id' => 'autre', 'lieu_provenance' => 'Djibo'],
            'déplacé, commune de la liste' => ['groupe_population' => 'pdi', 'lieu_provenance_commune_id' => (string) $autreCommune->id, 'lieu_provenance' => 'Effacé'],
            'déplacé, commune inconnue' => ['groupe_population' => 'pdi', 'lieu_provenance_commune_id' => '999999'],
            'communauté, provenance effacée' => ['groupe_population' => 'communaute', 'lieu_provenance_commune_id' => (string) $autreCommune->id, 'lieu_provenance' => 'Effacé'],
            'vulnérabilités, garçon' => ['sexe' => 'M', 'vulnerabilites' => ['autre', 'grossesse', 'orphelin'], 'vulnerabilite_precision' => 'Travail des enfants'],
            'vulnérabilité autre sans précision' => ['vulnerabilites' => ['autre']],
            'vulnérabilité inconnue' => ['vulnerabilites' => ['inexistante']],
            'précision de vulnérabilité effacée' => ['vulnerabilite_precision' => 'Effacée'],
            'lieu de vie autre sans précision' => ['lieu_de_vie' => 'autre'],
            'lieu de vie précision effacée' => ['lieu_de_vie_precision' => 'Effacée'],
            'école de la liste' => ['ecole_id' => (string) $ecole->id, 'etablissement_actuel' => ''],
            'école d’une autre commune' => ['ecole_id' => (string) $ecoleAilleurs->id],
            'école autre sans nom' => ['ecole_id' => 'autre', 'etablissement_actuel' => ''],
            'école précédente de la liste' => ['ecole_precedente_id' => (string) $ecolePrecedente->id, 'etablissement_precedent' => ''],
            'école précédente autre sans nom' => ['ecole_precedente_id' => 'autre', 'etablissement_precedent' => ''],
            'école précédente d’une autre commune' => ['ecole_precedente_id' => (string) $ecoleAilleurs->id],
            'moyenne hors barème' => ['moyenne_annuelle' => '15'],
            'moyenne sur 20' => ['classe_precedente' => '6e', 'classe' => '5e', 'moyenne_annuelle' => '14,5'],
            'moyenne sans classe précédente' => ['classe_precedente' => ''],
            'redoublant qui change de classe' => ['appreciation' => 'redouble'],
            'admis dans la même classe' => ['classe_precedente' => '6e', 'appreciation' => 'admis', 'moyenne_annuelle' => '12'],
            'classe inférieure à la précédente' => ['classe_precedente' => '5e', 'moyenne_annuelle' => '12'],
            'classe hors du niveau' => ['niveau_etude' => 'primaire'],
            'scolarisé sans établissement ni classe' => ['etablissement_actuel' => '', 'classe' => '', 'type_etablissement' => '', 'systeme_educatif' => '', 'performance_scolaire' => ''],
            'difficultés sans explication' => ['performance_scolaire' => 'difficultes'],
            'difficultés expliquées' => ['performance_scolaire' => 'difficultes', 'performance_difficultes' => 'Lecture'],
            'non scolarisé sans raison ni souhait' => $nonScolarise,
            'non scolarisé, raison autre et formation souhaitée' => $nonScolarise + ['raison_non_scolarisation' => 'autre', 'raison_non_scolarisation_precision' => 'Travaux champêtres',
                'souhait_avenir' => 'formation', 'souhait_avenir_precision' => 'Mécanique', 'frais_scolarite' => '1000'],
            'non scolarisé, raison autre sans précision' => $nonScolarise + ['raison_non_scolarisation' => 'autre', 'souhait_avenir' => 'ecole'],
            'déscolarisé exclu qui veut l’école' => $descolarise + ['appreciation' => 'exclu', 'raison_non_scolarisation' => 'financieres', 'souhait_avenir' => 'ecole'],
            'déscolarisé exclu sans projet' => $descolarise + ['appreciation' => 'exclu', 'raison_non_scolarisation' => 'financieres', 'souhait_avenir' => 'aucun', 'souhait_avenir_precision' => 'Effacée'],
            'déscolarisé sans niveau' => $descolarise + ['niveau_etude' => '', 'raison_non_scolarisation' => 'sante', 'souhait_avenir' => 'ne_sait_pas'],
            'éducation non formelle' => ['situation_scolaire' => 'non_formelle'],
            'formation en cours sans durée reçue' => $nonScolarise + ['raison_non_scolarisation' => 'financieres'] + $formation,
            'formation, durée reçue trop longue' => $nonScolarise + ['raison_non_scolarisation' => 'financieres', 'formation_duree_recue_mois' => '30'] + $formation,
            'formation en cours complète' => $nonScolarise + ['raison_non_scolarisation' => 'financieres', 'formation_duree_recue_mois' => '6', 'centre_formation_id' => (string) $centre->id] + $formation,
            'formation achevée' => $nonScolarise + ['raison_non_scolarisation' => 'financieres', 'formation_duree_recue_mois' => '3'] + ['formation_etat' => 'achevee'] + $formation,
            'formation, centre qui est une école' => $nonScolarise + ['raison_non_scolarisation' => 'financieres', 'formation_duree_recue_mois' => '6', 'centre_formation_id' => (string) $ecole->id] + $formation,
            'formation incomplète' => ['formation_professionnelle' => '1'],
            'champs de formation effacés' => ['formation_professionnelle' => '0', 'formation_filiere' => 'Effacée', 'formation_duree_mois' => '12'],
            'village inconnu' => ['village_id' => '999999'],
            'textes trop longs' => ['nom' => str_repeat('A', 101), 'quartier' => str_repeat('B', 151), 'rib_etablissement' => str_repeat('C', 61)],
            'formulaire vide' => array_fill_keys(array_keys($this->donneesOev()), ''),
        ];

        $cas = [];
        foreach ($scenarios as $nom => $surcharge) {
            $cas[$nom] = [$dp, $this->donneesOev($surcharge), []];
        }
        $cas['DR de la région'] = [$dr, $this->donneesOev(), []];
        $cas['compte sans localité'] = [$sansZone, $this->donneesOev(), []];
        $cas['DP d’une autre province'] = [$autreDp, $this->donneesOev(), []];
        $cas['pièces jointes'] = [$dp, $this->donneesOev(), [
            'acte_naissance' => UploadedFile::fake()->create('acte.pdf', 100, 'application/pdf'),
            'photo' => UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf'),
            'bulletin' => UploadedFile::fake()->create('bulletin.exe', 100, 'application/octet-stream'),
        ]];

        $avant = new ValidationOevAvant();
        $apres = app(ValidationOev::class);
        $valides = 0;
        foreach ($cas as $nom => [$utilisateur, $donnees, $fichiers]) {
            $attendu = $this->resultat(fn (Request $r) => $avant->valider($r), $utilisateur, $donnees, $fichiers);
            $obtenu = $this->resultat(fn (Request $r) => $apres->valider($r), $utilisateur, $donnees, $fichiers);
            $this->assertSame($attendu, $obtenu, "Scénario « {$nom} »");
            $valides += isset($attendu['donnees']) ? 1 : 0;
        }

        // Les scénarios couvrent à la fois des saisies acceptées et refusées
        $this->assertGreaterThan(15, $valides);
        $this->assertGreaterThan(15, count($cas) - $valides);
    }
}
