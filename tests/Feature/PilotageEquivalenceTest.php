<?php

namespace Tests\Feature;

use App\Models\AppuiPartenaire;
use App\Models\BeneficiaireSession;
use App\Models\Commune;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\SessionParrainage;
use App\Models\SuiviParrainage;
use App\Models\User;
use App\Services\Parrainage\ExtractionsParrainage;
use App\Services\Parrainage\PilotageParrainage;
use App\Services\Parrainage\SourceSelection;
use App\Services\Parrainage\SourceSelectionEnBase;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\Support\PilotageParrainageAvant;
use Tests\TestCase;

/**
 * Le tableau de bord « Pilotage » calculé par la base donne exactement les mêmes indicateurs que l'ancien
 * calcul en mémoire (tests/Support/PilotageParrainageAvant.php), pour toutes les combinaisons de filtres
 * (niveau de l'utilisateur, session, type d'appui, sexe) et des dossiers couvrant chaque valeur du profil.
 */
class PilotageEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    /** Listes de l'État fournies en mémoire (source externe à la base) : calcul par paquets. */
    public function test_le_calcul_en_base_equivaut_a_l_ancien_calcul_sur_toutes_les_combinaisons(): void
    {
        $this->verifierEquivalence(false);
    }

    /** Listes de l'État dans la base (module Sélection) : calcul entièrement par la base. */
    public function test_le_calcul_sur_les_listes_en_base_equivaut_a_l_ancien_calcul_sur_toutes_les_combinaisons(): void
    {
        $this->verifierEquivalence(true);
    }

    private function verifierEquivalence(bool $listesEnBase): void
    {
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);
        $ici = Commune::where('nom', 'Ouagadougou')->firstOrFail();
        // Une autre province de la même région (le DR la voit, pas le DP)
        $memeRegion = Commune::create([
            'province_id' => \App\Models\Province::create(['region_id' => $ici->province->region_id, 'nom' => 'Province voisine'])->id,
            'nom' => 'Commune voisine',
        ]);
        $ailleurs = Commune::whereHas('province', fn ($q) => $q->where('region_id', '!=', $ici->province->region_id))->firstOrFail();
        $utilisateurs = [
            'central' => User::factory()->create()->assignRole('agent DGFE'),
            'dr' => User::factory()->create(['region_id' => $ici->province->region_id])->assignRole('DR'),
            'dp' => User::factory()->create(['region_id' => $ici->province->region_id, 'province_id' => $ici->province_id])->assignRole('DP'),
        ];

        // Dossiers : chaque borne d’âge (veille, jour même, lendemain ; date de naissance obligatoire), chaque sexe, cycle, vulnérabilité, handicap
        $naissances = [now()->subYears(2)->toDateString()];
        foreach ([6, 12, 16, 18] as $ans) {
            $naissances[] = now()->subYears($ans)->subDay()->toDateString();
            $naissances[] = now()->subYears($ans)->toDateString();
            $naissances[] = now()->subYears($ans)->addDay()->toDateString();
        }
        $cycles = [[null, null], ['', false], [null, true], ['primaire', true]];
        foreach (array_keys(Oev::NIVEAUX_ETUDE) as $niveau) {
            $cycles[] = [$niveau, false];
            $cycles[] = [$niveau, null];
        }
        $vulnerabilites = array_merge([null, []], array_map(fn ($code) => [$code], array_keys(Oev::VULNERABILITES)), [['orphelin', 'travail', 'detresse']]);
        $communes = [$ici, $memeRegion, $ailleurs];
        $oevs = collect();
        $i = 0;
        foreach ($naissances as $naissance) {
            foreach (['F', 'M'] as $sexe) {
                foreach ([true, false] as $handicap) {
                    [$niveau, $formation] = $cycles[$i % count($cycles)];
                    $oevs->push($this->oev($communes[$i % 3], [
                        'date_naissance' => $naissance, 'sexe' => $sexe, 'handicap' => $handicap, 'niveau_etude' => $niveau,
                        'formation_professionnelle' => $formation, 'vulnerabilites' => $vulnerabilites[$i % count($vulnerabilites)],
                    ]));
                    $i++;
                }
            }
        }
        foreach ($cycles as $j => [$niveau, $formation]) {
            $oevs->push($this->oev($communes[$j % 3], ['niveau_etude' => $niveau, 'formation_professionnelle' => $formation, 'sexe' => $j % 2 ? 'F' : 'M']));
        }
        foreach ($vulnerabilites as $j => $liste) {
            $oevs->push($this->oev($communes[$j % 3], ['vulnerabilites' => $liste]));
        }
        // Désactivés : ni éligibles ni comptés
        $oevs->push($this->oev($ici, ['desactivation_etat' => Oev::DESACTIVE]));

        // Sessions de l'année, appuis des partenaires (natures variées, certains OEV aussi appuyés par l'État)
        $session = SessionParrainage::create([
            'description' => 'S1', 'annee' => '2026-2027', 'numero' => 1, 'type_appui' => SessionParrainage::TYPE_LES_DEUX,
            'enveloppe' => 5_000_000, 'date_ouverture' => '2026-10-01',
            'etat' => SessionParrainage::ETAT_VALIDEE, 'quotas_actifs' => true,
        ]);
        $session->quotas()->create(['region_id' => $ici->province->region_id, 'montant' => 1_000_000]);
        $session2 = SessionParrainage::create([
            'description' => 'S2', 'annee' => '2026-2027', 'numero' => 2, 'type_appui' => SessionParrainage::TYPE_SCOLAIRE,
            'enveloppe' => 2_000_000, 'date_ouverture' => '2026-11-01',
            'etat' => SessionParrainage::ETAT_VALIDEE,
        ]);
        $parrains = collect(range(1, 3))->map(fn ($n) => Parrain::create(['type' => 'ong', 'nom' => "Parrain {$n}", 'actif' => true]));
        $natures = NatureAppui::pluck('id')->values();
        $definitive = [];
        $attente = [];
        $resultats = [];
        foreach ($oevs->values() as $j => $oev) {
            if ($j % 3 === 0) {
                AppuiPartenaire::create(['oev_id' => $oev->id, 'parrain_id' => $parrains[$j % 3]->id, 'annee' => '2026-2027',
                    'nature_appui_id' => $natures[$j % $natures->count()], 'montant' => $j % 2 ? null : 10_000 + $j]);
            }
            if ($j % 4 === 0) {
                $definitive[] = ['oev_id' => $oev->id, 'type_appui' => $j % 8 ? 'scolaire' : 'formation_professionnelle', 'etablissement_id' => null,
                    'frais_reels' => 40_000, 'montant_retenu' => 20_000 + $j];
            } elseif ($j % 5 === 0) {
                $attente[] = ['oev_id' => $oev->id, 'rang' => $j, 'besoin_estime' => 30_000];
            }
            if ($j % 2 === 0) {
                $resultats[] = ['oev_id' => $oev->id, 'annee' => '2026-2027', 'resultat' => ['admis', 'redouble', 'abandon'][$j % 3]];
            }
        }
        if ($listesEnBase) {
            // Mêmes listes, enregistrées comme le fait le module Sélection (session selon la parité de l'OEV)
            $sessionDe = fn (int $oevId) => $oevId % 2 === 1 ? $session : $session2;
            foreach ($definitive as $j => $b) {
                $ligne = BeneficiaireSession::create(['session_parrainage_id' => $sessionDe($b['oev_id'])->id, 'oev_id' => $b['oev_id'],
                    'type_appui' => $b['type_appui'], 'statut' => BeneficiaireSession::RETENU, 'score' => 10,
                    'frais_reels' => $b['frais_reels'], 'frais_estimes' => false, 'montant_retenu' => $b['montant_retenu']]);
                if ($j % 3 !== 2) {
                    SuiviParrainage::create(['beneficiaire_session_id' => $ligne->id, 'oev_id' => $b['oev_id'], 'annee' => '2026-2027',
                        'type_appui' => $b['type_appui'], 'resultat' => ['admis', 'redouble', 'abandon'][$j % 3], 'a_reconduire' => true]);
                }
            }
            $source = $this->app->make(SourceSelection::class);
            $this->assertInstanceOf(SourceSelectionEnBase::class, $source);
        } else {
            $source = $this->source($definitive, $attente, $resultats);
        }
        $nouveau = new PilotageParrainage($source);
        $ancien = new PilotageParrainageAvant($source);

        $combinaisons = 0;
        foreach ($utilisateurs as $niveau => $utilisateur) {
            foreach (['', $session->id] as $sessionId) {
                foreach (array_merge([''], array_keys(SessionParrainage::TYPES_APPUI)) as $type) {
                    foreach (['', 'F', 'M'] as $sexe) {
                        $f = $nouveau->filtres(Request::create('/', 'GET', ['annee' => '2026-2027', 'session_id' => $sessionId, 'type_appui' => $type, 'sexe' => $sexe]), $utilisateur);
                        $this->assertEquals(
                            $this->normaliser($ancien->indicateurs($f)),
                            $this->normaliser($nouveau->indicateurs($f)),
                            "Écart pour {$niveau}, session « {$sessionId} », type « {$type} », sexe « {$sexe} »",
                        );
                        $combinaisons++;
                    }
                }
            }
        }
        $this->assertSame(3 * 2 * (1 + count(SessionParrainage::TYPES_APPUI)) * 3, $combinaisons);

        // Liste pour paiement : le nombre compté d'avance (contrôle de taille de l'export) est celui de la liste produite
        if ($listesEnBase) {
            $extractions = $this->app->make(ExtractionsParrainage::class);
            foreach ($utilisateurs as $utilisateur) {
                foreach ([$session, $session2] as $s) {
                    foreach (array_merge([''], array_keys(SessionParrainage::TYPES_APPUI)) as $type) {
                        foreach ([[], ['region_id' => $ici->province->region_id], ['province_id' => $ici->province_id], ['commune_id' => $ailleurs->id]] as $zone) {
                            $filtres = array_filter(['type_appui' => $type] + $zone);
                            $this->assertSame($extractions->paiement($s, $utilisateur, $filtres)['total_oev'], $extractions->nombrePaiement($s, $utilisateur, $filtres));
                        }
                    }
                }
            }
        }
        // Le jeu de données exerce bien les indicateurs (pas une égalité entre deux résultats vides)
        $f = $nouveau->filtres(Request::create('/', 'GET', ['annee' => '2026-2027']), $utilisateurs['central']);
        $i = $nouveau->indicateurs($f);
        $this->assertGreaterThan(10, $i['profil']['total']);
        $this->assertGreaterThan(0, $i['couverture']['etat']);
        $this->assertGreaterThan(0, $i['couverture']['partenaires']);
        $this->assertCount(5, $i['profil']['age']->filter(fn ($l) => $l['total'] > 0));
        $this->assertGreaterThan(0, collect($i['resultats'])->sum('total'));
    }

    /** Collections converties en tableaux : seules les valeurs comptent. */
    private function normaliser(mixed $valeur): mixed
    {
        if ($valeur instanceof Collection) {
            $valeur = $valeur->all();
        }

        return is_array($valeur) ? array_map(fn ($v) => $this->normaliser($v), $valeur) : $valeur;
    }

    private function oev(Commune $commune, array $attributs = []): Oev
    {
        return Oev::create($attributs + [
            'nom' => 'ENFANT', 'prenom' => Str::random(6), 'sexe' => 'F', 'date_naissance' => now()->subYears(10)->toDateString(),
            'statut' => 'orphelin_pere', 'handicap' => false, 'systeme_educatif' => 'classique', 'niveau_etude' => 'primaire',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
            'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
        ]);
    }

    private function source(array $definitive, array $attente, array $resultats): SourceSelection
    {
        return new class($definitive, $attente, $resultats) implements SourceSelection
        {
            public function __construct(private array $definitive, private array $attente, private array $resultats)
            {
            }

            public function disponible(): bool
            {
                return true;
            }

            public function eligiblesParRegion(SessionParrainage $session): array
            {
                return [];
            }

            public function montantEngage(SessionParrainage $session): ?int
            {
                return null;
            }

            /** Les listes sont réparties entre les sessions (numéro pair / impair) pour exercer la fusion. */
            public function listeDefinitive(SessionParrainage $session): Collection
            {
                return collect($this->definitive)->filter(fn ($b) => $b['oev_id'] % 2 === $session->numero % 2)->values();
            }


            public function etablissements(): Collection
            {
                return collect();
            }

            public function naturesAppuiEtat(int $oevId, string $annee): array
            {
                return [];
            }

            public function resultatsScolaires(string $annee): Collection
            {
                return collect($this->resultats)->where('annee', $annee)->values();
            }
        };
    }
}
