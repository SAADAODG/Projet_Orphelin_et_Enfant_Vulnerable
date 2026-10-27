<?php

namespace Tests\Feature;

use App\Models\BeneficiaireSession;
use App\Models\Oev;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Services\Parrainage\SelectionParrainage;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'estimation du coût des éligibles, calculée par la base en une requête, donne exactement le même résultat que
 * l'ancien parcours de tous les éligibles en PHP (reproduit ci-dessous à l'identique), pour toutes les
 * combinaisons de session (type d'appui, plafond, montants estimatifs, financements antérieurs) et des
 * dossiers couvrant chaque situation de scolarité et de formation et chaque cas de frais.
 */
class EstimationCoutEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    /** Ancien calcul (avant le passage en SQL), conservé tel quel. */
    private function ancienneEstimation(SessionParrainage $session, Builder $eligibles): array
    {
        $selection = app(SelectionParrainage::class);
        $groupes = array_fill_keys(array_keys(SelectionParrainage::SITUATIONS), ['nombre' => 0, 'frais' => 0, 'sans_frais' => 0, 'pris_en_charge' => 0]);
        $dejaFinance = $selection->dejaFinance($session);

        $eligibles->reorder()
            ->select(['oevs.id', 'oevs.date_naissance', 'oevs.situation_scolaire', 'oevs.formation_professionnelle', 'oevs.formation_etat', 'oevs.frais_scolarite', 'oevs.frais_formation'])
            ->lazyById(500, 'oevs.id', 'id')
            ->each(function (Oev $oev) use ($session, &$groupes, $dejaFinance) {
                $type = SelectionParrainage::typePour($oev, $session);
                $groupe = $type === SessionParrainage::TYPE_FORMATION ? SelectionParrainage::EN_FORMATION : ($oev->situation_scolaire === 'scolarise' ? SelectionParrainage::SCOLARISE : SelectionParrainage::A_SCOLARISER);
                ['frais' => $frais] = SelectionParrainage::fraisPour($oev, $session, $type);
                $frais = SelectionParrainage::fraisRestants($frais, $dejaFinance[$oev->id] ?? 0);

                $groupes[$groupe]['nombre']++;
                $groupes[$groupe]['frais'] += (int) $frais;
                $groupes[$groupe]['sans_frais'] += $frais === null ? 1 : 0;
                $groupes[$groupe]['pris_en_charge'] += BeneficiaireSession::montantPour($frais, $session->plafond_beneficiaire);
            });

        return [
            'groupes' => $groupes,
            'nombre' => array_sum(array_column($groupes, 'nombre')),
            'frais' => array_sum(array_column($groupes, 'frais')),
            'pris_en_charge' => array_sum(array_column($groupes, 'pris_en_charge')),
        ];
    }

    public function test_l_estimation_en_base_equivaut_a_l_ancien_parcours_sur_toutes_les_combinaisons(): void
    {
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class]);
        $region = Region::has('provinces')->firstOrFail();

        // Dossiers : situation scolaire × formation × âge (scolaire ou non) × frais (absents, sous / au-dessus du plafond)
        $n = 0;
        foreach (['scolarise', 'non_scolarise', 'descolarise', null] as $situation) {
            foreach ([[true, 'en_cours'], [true, 'termine'], [false, null], [null, null]] as [$formation, $etat]) {
                foreach ([10, 20] as $age) {
                    foreach ([null, 30_000, 90_000] as $frais) {
                        $n++;
                        Oev::create([
                            'nom' => 'OEV ' . $n, 'prenom' => 'Test', 'sexe' => 'F', 'date_naissance' => now()->subYears($age)->toDateString(),
                            'statut' => 'vulnerable', 'handicap' => false, 'situation_scolaire' => $situation,
                            'formation_professionnelle' => $formation, 'formation_etat' => $etat,
                            'frais_scolarite' => $frais, 'frais_formation' => $frais === null ? null : $frais + 5_000, 'niveau_priorite' => 'moyen',
                            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
                            'region_id' => $region->id, 'province_id' => $region->provinces()->value('id'),
                            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
                            'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
                        ]);
                    }
                }
            }
        }
        $ids = Oev::orderBy('id')->pluck('id');

        // Session antérieure de l'année (validée) : financements partiels, complets et inconnus
        $anterieure = SessionParrainage::create([
            'description' => 'Antérieure', 'annee' => '2026-2027', 'numero' => 1, 'type_appui' => SessionParrainage::TYPE_LES_DEUX,
            'enveloppe' => 9_000_000, 'date_ouverture' => '2026-09-01',
            'etat' => SessionParrainage::ETAT_VALIDEE,
        ]);
        foreach ($ids as $i => $id) {
            if ($i % 4 === 0) {
                BeneficiaireSession::create(['session_parrainage_id' => $anterieure->id, 'oev_id' => $id, 'statut' => BeneficiaireSession::RETENU,
                    'type_appui' => $i % 8 ? SessionParrainage::TYPE_SCOLAIRE : SessionParrainage::TYPE_FORMATION, 'score' => 10, 'region_id' => $region->id,
                    'frais_reels' => $i % 3 ? 90_000 : null, 'frais_estimes' => false, 'montant_retenu' => [10_000, 25_000, 90_000][$i % 3]]);
            }
        }

        $selection = app(SelectionParrainage::class);
        $numero = 10;
        $combinaisons = 0;
        foreach (SessionParrainage::TYPES_APPUI as $type => $libelle) {
            foreach ([null, 50_000] as $plafond) {
                foreach ([[null, null], [20_000, 60_000]] as [$scolarisation, $formation]) {
                    foreach ([[], ['sexe' => ['F']], ['scolarite' => [SelectionParrainage::A_SCOLARISER]]] as $filtres) {
                        $session = SessionParrainage::create([
                            'description' => 'Combinaison', 'annee' => '2026-2027', 'numero' => $numero++, 'type_appui' => $type,
                            'enveloppe' => 9_000_000, 'plafond_beneficiaire' => $plafond, 'estimation_scolarisation' => $scolarisation,
                            'estimation_formation' => $formation,
                            'date_ouverture' => '2026-10-01', 'etat' => SessionParrainage::ETAT_EN_COURS,
                        ]);
                        $attendu = $this->ancienneEstimation($session, $selection->eligibles($session, $filtres));
                        $this->assertSame($attendu, $selection->estimationCout($session, $selection->eligibles($session, $filtres)),
                            "Écart : {$type}, plafond " . ($plafond ?? 'aucun') . ', estimations ' . ($scolarisation ?? 'aucune') . ', filtres ' . json_encode($filtres));
                        $combinaisons++;
                        $session->delete();
                    }
                }
            }
        }
        $this->assertSame(count(SessionParrainage::TYPES_APPUI) * 2 * 2 * 3, $combinaisons);

        // Le jeu de données exerce bien les trois situations
        $session = SessionParrainage::create(['description' => 'Contrôle', 'annee' => '2026-2027', 'numero' => 99, 'type_appui' => SessionParrainage::TYPE_LES_DEUX,
            'enveloppe' => 1, 'plafond_beneficiaire' => 50_000, 'date_ouverture' => '2026-10-01',
            'etat' => SessionParrainage::ETAT_EN_COURS]);
        $estimation = $selection->estimationCout($session, $selection->eligibles($session));
        foreach ($estimation['groupes'] as $groupe => $totaux) {
            $this->assertGreaterThan(0, $totaux['nombre'], "Aucun OEV dans le groupe {$groupe}");
        }
    }
}
