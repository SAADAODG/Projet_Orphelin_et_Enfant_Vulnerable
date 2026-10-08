<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Oev;
use App\Models\User;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractionTest extends TestCase
{
    use RefreshDatabase;

    private Commune $ouagadougou;
    private Commune $ailleurs;
    private User $central;
    private User $dp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);
        $this->ouagadougou = Commune::where('nom', 'Ouagadougou')->firstOrFail();
        $this->ailleurs = Commune::where('province_id', '!=', $this->ouagadougou->province_id)->firstOrFail();

        $this->central = User::factory()->create()->assignRole('agent DGFE');
        $province = $this->ouagadougou->province;
        $this->dp = User::factory()->create(['region_id' => $province->region_id, 'province_id' => $province->id])->assignRole('DP');
    }

    private function enfant(array $champs = [], ?Commune $commune = null): Oev
    {
        $commune ??= $this->ouagadougou;
        static $n = 0;
        $n++;

        return Oev::create($champs + [
            'numero_dossier' => 'DOS-2026-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'code' => 'OEV-2026-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'statut_dossier' => Oev::ETAT_INTEGRE,
            'integre_at' => now(),
            'nom' => 'ENFANT' . $n,
            'prenom' => 'Test',
            'sexe' => 'F',
            'date_naissance' => now()->subYears(10)->toDateString(),
            'statut' => 'orphelin_pere',
            'mere_vivante' => 'oui',
            'pere_vivant' => 'non',
            'handicap' => false,
            'situation_scolaire' => 'scolarise',
            'nom_tuteur' => 'TUTEUR',
            'prenom_tuteur' => 'Issa',
            'contact_tuteur' => '+226 70 00 00 00',
            'region_id' => $commune->province->region_id,
            'province_id' => $commune->province_id,
            'commune_id' => $commune->id,
        ]);
    }

    public function test_la_page_s_affiche_depuis_le_menu(): void
    {
        $this->enfant(['nom' => 'KABORE']);

        $this->actingAs($this->central)->get(route('extraction.index'))->assertOk()
            ->assertSee('Filtrage et extraction')
            ->assertSee('KABORE')
            ->assertSee(route('extraction.index'), false);
    }

    public function test_les_filtres_se_combinent(): void
    {
        $this->enfant(['nom' => 'FILLE_HANDICAP', 'handicap' => true, 'types_handicap' => ['visuel'], 'vulnerabilites' => ['orphelin', 'handicap']]);
        $this->enfant(['nom' => 'GARCON', 'sexe' => 'M']);
        $this->enfant(['nom' => 'PETITE', 'date_naissance' => now()->subYears(4)->toDateString()]);
        $this->enfant(['nom' => 'NON_SCO', 'situation_scolaire' => 'non_scolarise']);
        $this->enfant(['nom' => 'BROUILLON', 'statut_dossier' => Oev::ETAT_BROUILLON, 'code' => null]);
        $this->actingAs($this->central);

        $this->get(route('extraction.index', ['sexe' => 'M']))->assertSee('GARCON')->assertDontSee('FILLE_HANDICAP');
        $this->get(route('extraction.index', ['handicap' => '1', 'type_handicap' => 'visuel']))->assertSee('FILLE_HANDICAP')->assertDontSee('GARCON');
        $this->get(route('extraction.index', ['vulnerabilite' => 'handicap']))->assertSee('FILLE_HANDICAP')->assertDontSee('PETITE');
        $this->get(route('extraction.index', ['age_max' => 5]))->assertSee('PETITE')->assertDontSee('GARCON');
        $this->get(route('extraction.index', ['age_min' => 6, 'sexe' => 'F', 'situation_scolaire' => 'scolarise']))
            ->assertSee('FILLE_HANDICAP')->assertDontSee('PETITE')->assertDontSee('NON_SCO')->assertDontSee('GARCON');
        $this->get(route('extraction.index', ['q' => 'garc']))->assertSee('GARCON')->assertDontSee('PETITE');
        // Le niveau central ne voit pas les dossiers encore en constitution
        $this->get(route('extraction.index'))->assertDontSee('BROUILLON');
        // Une valeur inconnue est ignorée
        $this->get(route('extraction.index', ['sexe' => 'X']))->assertOk()->assertSee('GARCON')->assertSee('PETITE');
    }

    public function test_le_dp_ne_voit_que_sa_province(): void
    {
        $this->enfant(['nom' => 'CHEZ_MOI']);
        $this->enfant(['nom' => 'AILLEURS'], $this->ailleurs);

        $this->actingAs($this->dp)->get(route('extraction.index'))->assertOk()->assertSee('CHEZ_MOI')->assertDontSee('AILLEURS');
        $csv = $this->get(route('extraction.export'))->streamedContent();
        $this->assertStringContainsString('CHEZ_MOI', $csv);
        $this->assertStringNotContainsString('AILLEURS', $csv);
    }

    public function test_l_export_csv_reprend_les_filtres(): void
    {
        $this->enfant(['nom' => 'GARCON', 'sexe' => 'M', 'gestionnaire_nom' => 'KABORE Issa']);
        $this->enfant(['nom' => 'FILLE']);

        $reponse = $this->actingAs($this->central)->get(route('extraction.export', ['sexe' => 'M']));
        $reponse->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $reponse->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'BOM UTF-8 pour Excel');
        $lignes = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $lignes, 'En-tête + un seul dossier');
        $this->assertStringContainsString('"Code OEV";"N° de dossier"', $lignes[0]);
        $this->assertStringContainsString('GARCON', $lignes[1]);
        $this->assertStringContainsString('Masculin', $lignes[1]);
        $this->assertStringContainsString('KABORE Issa', $lignes[1]);
        $this->assertStringNotContainsString('FILLE', $csv);
    }

    public function test_un_utilisateur_sans_droit_n_y_a_pas_acces(): void
    {
        $this->actingAs(User::factory()->create())->get(route('extraction.index'))->assertAccesRefuse();
        $this->get(route('extraction.export'))->assertAccesRefuse();
    }
}
