<?php

namespace Tests\Feature;

use App\Models\AppuiPartenaire;
use App\Models\BeneficiaireSession;
use App\Models\Commune;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Services\Parrainage\ExtractionsParrainage;
use App\Services\Parrainage\SourceSelection;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ExtractionsParrainageAvant;
use Tests\TestCase;

/**
 * La liste d'OEV pour un parrain, triée et comptée par la base et lue par paquets, contient exactement les
 * mêmes OEV, dans le même ordre et avec le même besoin estimé, que l'ancien tri en mémoire
 * (tests/Support/ExtractionsParrainageAvant.php), pour toutes les combinaisons de source et de filtres.
 */
class ListeParrainEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_lue_par_paquets_equivaut_a_l_ancienne_liste_sur_toutes_les_combinaisons(): void
    {
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);
        $ici = Commune::where('nom', 'Ouagadougou')->firstOrFail();
        $ailleurs = Commune::whereHas('province', fn ($q) => $q->where('region_id', '!=', $ici->province->region_id))->firstOrFail();
        $central = User::factory()->create()->assignRole('agent DGFE');
        $dr = User::factory()->create(['region_id' => $ici->province->region_id])->assignRole('DR');
        $parrain = Parrain::create(['type' => 'ong', 'nom' => 'ONG', 'actif' => true]);
        $session = SessionParrainage::create([
            'description' => 'S1', 'annee' => '2026-2027', 'numero' => 1, 'type_appui' => SessionParrainage::TYPE_SCOLAIRE,
            'enveloppe' => 1_000_000, 'date_ouverture' => '2026-10-01',
            'etat' => SessionParrainage::ETAT_VALIDEE,
        ]);
        $scolaire = NatureAppui::where('code', 'scolaire')->firstOrFail();
        $sante = NatureAppui::where('code', 'sante')->firstOrFail();

        // Noms piégeux pour un tri octet par octet sur « nom + prénom » : préfixes, casse, accents, espaces
        $noms = [['AB', 'C'], ['A', 'BZ'], ['ab', 'c'], ['ÉLODIE', 'A'], ['ELODIE', 'B'], ['A B', 'C'], ['ZONGO', ''], ['zongo', 'a'],
            ['KABORE', 'Awa'], ['KABORE', 'awa'], ['OUEDRAOGO', 'Issa'], ['OUÉDRAOGO', 'Issa']];
        $priorites = [null, 'eleve', 'moyen', 'faible'];
        $oevs = collect();
        foreach ($noms as $i => [$nom, $prenom]) {
            foreach ($priorites as $j => $priorite) {
                $oevs->push(Oev::create([
                    'nom' => $nom, 'prenom' => $prenom . ($j ? (string) $j : ''), 'sexe' => ($i + $j) % 2 ? 'F' : 'M',
                    'date_naissance' => now()->subYears(6 + ($i + $j) % 12)->toDateString(), 'statut' => 'orphelin_pere',
                    'handicap' => ($i * $j) % 3 === 0, 'systeme_educatif' => 'classique', 'niveau_etude' => 'primaire', 'niveau_priorite' => $priorite,
                    'frais_scolarite' => ($i + $j) % 4 ? 20_000 + $i * 1000 : null,
                    'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
                    'region_id' => ($j % 2 ? $ailleurs : $ici)->province->region_id, 'province_id' => ($j % 2 ? $ailleurs : $ici)->province_id,
                    'commune_id' => ($j % 2 ? $ailleurs : $ici)->id, 'numero_dossier' => Oev::genererNumeroDossier(), 'code' => Oev::genererCode(),
                    'statut_dossier' => Oev::ETAT_INTEGRE, 'integre_at' => now(),
                ]));
            }
        }
        foreach ($oevs->values() as $k => $oev) {
            if ($k % 5 === 0) {
                AppuiPartenaire::create(['oev_id' => $oev->id, 'parrain_id' => $parrain->id, 'annee' => '2026-2027', 'nature_appui_id' => ($k % 2 ? $sante : $scolaire)->id]);
            }
            // Liste définitive de la session (bénéficiaires de l'État, exclus pour une nature scolaire)
            if ($k % 7 === 0) {
                BeneficiaireSession::create(['session_parrainage_id' => $session->id, 'oev_id' => $oev->id, 'type_appui' => 'scolaire',
                    'statut' => BeneficiaireSession::RETENU, 'score' => 10, 'region_id' => $oev->region_id,
                    'frais_reels' => 30_000, 'frais_estimes' => false, 'montant_retenu' => 30_000]);
            }
        }

        $source = $this->app->make(SourceSelection::class);
        $nouveau = new ExtractionsParrainage($source);
        $ancien = new ExtractionsParrainageAvant($source);
        $combinaisons = 0;
        foreach (['eligibles'] as $origine) {
            foreach ([null, $scolaire, $sante] as $nature) {
                foreach ([[], [$ici->province->region_id]] as $regions) {
                    foreach ([[], ['F'], ['M', 'F']] as $sexe) {
                        foreach ([[], ['1'], ['0'], ['1', '0']] as $handicap) {
                            foreach ([[], ['eleve', 'faible']] as $priorite) {
                                foreach (['central' => $central, 'dr' => $dr] as $niveau => $utilisateur) {
                                    $f = [
                                        'source' => $origine, 'session' => $session, 'annee' => '2026-2027', 'nature' => $nature, 'zone_parrain' => false,
                                        'regions' => $regions, 'provinces' => [], 'sexe' => $sexe, 'statut' => [], 'vulnerabilite' => [],
                                        'niveau_priorite' => $priorite, 'niveau_etude' => [], 'handicap' => $handicap, 'age_min' => null, 'age_max' => null,
                                    ];
                                    $attendu = $ancien->listeParrain($parrain, $utilisateur, $f)->map(fn (Oev $o) => [$o->id, $o->besoin_estime])->all();
                                    $liste = $nouveau->listeParrain($parrain, $utilisateur, $f);
                                    $obtenu = $liste['oevs']->map(fn (Oev $o) => [$o->id, $o->besoin_estime])->values()->all();
                                    $contexte = "{$origine}, nature " . ($nature?->code ?? 'aucune') . ', zone ' . ($regions ? 'région' : 'toutes')
                                        . ', sexe ' . implode('+', $sexe) . ', handicap ' . implode('+', $handicap) . ', priorité ' . implode('+', $priorite) . ", {$niveau}";
                                    $this->assertSame($attendu, $obtenu, "Écart pour {$contexte}");
                                    $this->assertSame(count($attendu), $liste['nombre'], "Nombre faux pour {$contexte}");
                                    $apercu = array_map(fn (Oev $o) => [$o->id, $o->besoin_estime], ($liste['apercu'])(10));
                                    $this->assertSame(array_slice($attendu, 0, 10), $apercu, "Aperçu faux pour {$contexte}");
                                    $combinaisons++;
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->assertSame(3 * 2 * 3 * 4 * 2 * 2, $combinaisons);
        // Le jeu de données exerce bien la liste (pas une égalité entre listes vides)
        $base = ['session' => $session, 'annee' => '2026-2027', 'nature' => null, 'zone_parrain' => false, 'regions' => [], 'provinces' => [],
            'sexe' => [], 'statut' => [], 'vulnerabilite' => [], 'niveau_priorite' => [], 'niveau_etude' => [], 'handicap' => [], 'age_min' => null, 'age_max' => null];
        $this->assertGreaterThan(30, $nouveau->listeParrain($parrain, $central, ['source' => 'eligibles'] + $base)['nombre']);
    }
}
