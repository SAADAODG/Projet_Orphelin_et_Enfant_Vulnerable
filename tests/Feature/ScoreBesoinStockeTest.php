<?php

namespace Tests\Feature;

use App\Models\JournalActivite;
use App\Models\Oev;
use App\Models\User;
use App\Services\Parrainage\ScoreBesoin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Score d'urgence enregistré (oevs.score_besoin) : il vaut exactement l'ancien calcul — en PHP
 * (ScoreBesoin::calculer) comme en SQL (ScoreBesoin::sql) — pour toutes les combinaisons des critères du
 * barème, et il est tenu à jour après chaque type de modification, sans apparaître dans le journal d'audit.
 */
class ScoreBesoinStockeTest extends TestCase
{
    use RefreshDatabase;

    private const PAR_DEFAUT = [
        'nom' => 'ENFANT', 'prenom' => 'Test', 'sexe' => 'F', 'date_naissance' => '2015-01-01', 'statut' => 'vulnerable',
        'handicap' => false, 'statut_dossier' => Oev::ETAT_INTEGRE,
        'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00', 'systeme_educatif' => 'classique',
    ];

    /** Dossiers insérés directement en base (comme un import), sans passer par le modèle. */
    private function inserer(array $lignes): void
    {
        $maintenant = now()->toDateTimeString();
        foreach (array_chunk($lignes, 500) as $paquet) {
            // Mêmes colonnes sur chaque ligne : critères absents = non renseignés
            $criteres = array_fill_keys(array_diff(ScoreBesoin::CHAMPS, array_keys(self::PAR_DEFAUT)), null);
            DB::table('oevs')->insert(array_map(fn ($l) => $l + self::PAR_DEFAUT + $criteres + ['created_at' => $maintenant, 'updated_at' => $maintenant], $paquet));
        }
    }

    /** Compare, pour chaque dossier, le score enregistré à l'ancien calcul PHP et à l'ancienne expression SQL. */
    private function verifierTousLesDossiers(): int
    {
        $verifies = 0;
        Oev::query()->select('oevs.*')->selectRaw(ScoreBesoin::sql() . ' as score_sql')->chunkById(2000, function ($oevs) use (&$verifies) {
            foreach ($oevs as $oev) {
                $attendu = ScoreBesoin::calculer($oev);
                $this->assertSame($attendu, (int) $oev->score_sql, "Calcul SQL ≠ calcul PHP pour le dossier {$oev->id}");
                $this->assertSame($attendu, (int) $oev->score_besoin, "Score enregistré ≠ ancien calcul pour le dossier {$oev->id}");
                $verifies++;
            }
        });

        return $verifies;
    }

    public function test_le_score_enregistre_equivaut_a_l_ancien_calcul_sur_toutes_les_combinaisons(): void
    {
        $avecNull = fn (array $liste) => array_merge([null], array_keys($liste));
        $bareme = collect(ScoreBesoin::BAREME)->mapWithKeys(fn ($critere) => [$critere[1] => $critere[2]]);

        // Tous les croisements des critères à choix (avec « non renseigné ») et des oui / non
        $lignes = [];
        foreach ($avecNull($bareme['niveau_priorite']) as $priorite) {
            foreach (array_keys(Oev::STATUTS) as $statut) {
                foreach ($avecNull($bareme['source_revenu']) as $source) {
                    foreach ($avecNull($bareme['niveau_revenu']) as $revenu) {
                        foreach ($avecNull($bareme['logement']) as $logement) {
                            foreach ([true, false] as $handicap) {
                                foreach ([null, true, false] as $maladie) {
                                    foreach ([null, true, false] as $tuteur) {
                                        $lignes[] = ['niveau_priorite' => $priorite, 'statut' => $statut, 'source_revenu' => $source,
                                            'niveau_revenu' => $revenu, 'logement' => $logement, 'handicap' => $handicap,
                                            'maladie_chronique' => $maladie, 'tuteur_pret_continuer' => $tuteur];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // Vulnérabilités : chaque sous-ensemble des vulnérabilités graves, avec ou sans une autre ; aucune ; liste vide
        $graves = ScoreBesoin::VULNERABILITES_GRAVES;
        $lignes[] = ['vulnerabilites' => null];
        $lignes[] = ['vulnerabilites' => '[]'];
        for ($masque = 0; $masque < 2 ** count($graves); $masque++) {
            $sousEnsemble = array_values(array_filter($graves, fn ($code, $i) => $masque & (1 << $i), ARRAY_FILTER_USE_BOTH));
            $lignes[] = ['vulnerabilites' => json_encode($sousEnsemble)];
            $lignes[] = ['vulnerabilites' => json_encode(array_merge(['orphelin'], $sousEnsemble, ['precarite']))];
        }
        $this->inserer($lignes);

        // Dossiers existants : remplis comme par la migration
        ScoreBesoin::recalculerTout();
        $attendus = 4 * 4 * 5 * 4 * 7 * 2 * 3 * 3 + 2 + 2 * 2 ** count($graves);
        $this->assertSame($attendus, $this->verifierTousLesDossiers());
        // Le jeu couvre bien tout l'éventail du score (le statut, obligatoire, vaut au moins 6 points)
        $this->assertSame(6, (int) Oev::min('score_besoin'));
        $this->assertSame(90, (int) Oev::max('score_besoin'));
    }

    public function test_le_score_est_tenu_a_jour_apres_chaque_type_de_modification_sans_polluer_le_journal(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole('DP'));

        // Création par le modèle
        $oev = Oev::create(self::PAR_DEFAUT + ['niveau_priorite' => 'faible', 'numero_dossier' => Oev::genererNumeroDossier()]);
        $this->assertSame(ScoreBesoin::calculer($oev), (int) $oev->fresh()->score_besoin);
        $this->assertSame((int) $oev->fresh()->score_besoin, (int) $oev->score_besoin, 'La fiche en mémoire reçoit le score');

        // Modification de chaque critère, un par un
        $modifications = [
            'niveau_priorite' => 'eleve', 'statut' => 'orphelin_double', 'source_revenu' => 'mendicite', 'niveau_revenu' => 'faible',
            'logement' => 'sans_abri', 'handicap' => true, 'maladie_chronique' => true, 'vulnerabilites' => ['abandon', 'travail'],
            'tuteur_pret_continuer' => false,
        ];
        $this->assertEqualsCanonicalizing(ScoreBesoin::CHAMPS, array_keys($modifications));
        $precedent = (int) $oev->fresh()->score_besoin;
        foreach ($modifications as $champ => $valeur) {
            $oev->update([$champ => $valeur]);
            $enBase = (int) $oev->fresh()->score_besoin;
            $this->assertSame(ScoreBesoin::calculer($oev->fresh()), $enBase, "Score non recalculé après modification de {$champ}");
            $this->assertGreaterThan($precedent, $enBase, "La modification de {$champ} augmente le score");
            $precedent = $enBase;
        }
        // Retour en arrière (vulnérabilités retirées, tuteur à nouveau présent)
        $oev->update(['vulnerabilites' => [], 'tuteur_pret_continuer' => true]);
        $this->assertSame(ScoreBesoin::calculer($oev->fresh()), (int) $oev->fresh()->score_besoin);

        // Modification d'un champ hors barème : pas de recalcul (aucune requête de mise à jour du score)
        $requetes = [];
        DB::listen(function ($q) use (&$requetes) { $requetes[] = $q->sql; });
        $oev->update(['prenom' => 'Autre']);
        $this->assertEmpty(array_filter($requetes, fn ($sql) => str_contains($sql, 'score_besoin')), implode("
", $requetes));

        // Journal d'audit : le score n'est jamais signalé comme champ modifié
        $champsTraces = JournalActivite::where('sujet_id', $oev->id)->get()->flatMap(fn ($e) => $e->details['champs'] ?? []);
        $this->assertNotEmpty($champsTraces);
        $this->assertNotContains('score_besoin', $champsTraces->all());

        // Mode différé (traitement en masse) : recalcul en une fois à la fin
        $autre = null;
        ScoreBesoin::differer(function () use ($oev, &$autre) {
            $oev->update(['niveau_priorite' => 'faible']);
            $autre = Oev::create(self::PAR_DEFAUT + ['niveau_priorite' => 'eleve', 'numero_dossier' => Oev::genererNumeroDossier()]);
            // Pendant le traitement, les scores ne sont pas encore recalculés
            $this->assertSame(0, (int) $autre->fresh()->score_besoin);
        });
        $this->assertSame(ScoreBesoin::calculer($oev->fresh()), (int) $oev->fresh()->score_besoin);
        $this->assertSame(ScoreBesoin::calculer($autre->fresh()), (int) $autre->fresh()->score_besoin);
        $this->assertGreaterThan(0, (int) $autre->fresh()->score_besoin);
    }
}
