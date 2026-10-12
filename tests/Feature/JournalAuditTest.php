<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\JournalActivite;
use App\Models\Oev;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $dr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LocaliteSeeder::class, CommuneSeeder::class]);

        $this->admin = User::factory()->create(['email' => 'admin@oev.bf', 'password' => bcrypt('secret-123')])->assignRole('administrateur');
        $this->dr = User::factory()->create(['region_id' => Region::value('id')])->assignRole('DR');
    }

    public function test_les_actions_sans_utilisateur_connecte_ne_sont_pas_tracees(): void
    {
        // Seeders, commandes, création en console : aucune entrée
        $this->assertSame(0, JournalActivite::count());
    }

    public function test_connexion_echec_et_deconnexion_sont_traces(): void
    {
        $this->post(route('login.submit'), ['email' => 'admin@oev.bf', 'password' => 'mauvais'])->assertSessionHasErrors('email');
        $this->post(route('login.submit'), ['email' => 'admin@oev.bf', 'password' => 'secret-123'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->assertSame(['auth.echec', 'auth.connexion', 'auth.deconnexion'], JournalActivite::orderBy('id')->pluck('action')->all());
        $echec = JournalActivite::where('action', 'auth.echec')->sole();
        $this->assertSame('admin@oev.bf', $echec->details['email']);
        $this->assertStringNotContainsString('mauvais', json_encode($echec->details), 'Le mot de passe saisi n’est jamais noté');
        $this->assertSame($this->admin->id, JournalActivite::where('action', 'auth.connexion')->value('user_id'));
    }

    public function test_creation_d_utilisateur_role_et_mot_de_passe(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Agent Central', 'email' => 'agent@oev.bf', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1', 'role' => 'agent DGFE',
        ])->assertSessionHasNoErrors();

        $agent = User::where('email', 'agent@oev.bf')->sole();
        $this->assertTrue(JournalActivite::where('action', 'utilisateur.creation')->where('sujet_id', $agent->id)->exists());
        $this->assertStringContainsString('agent DGFE', JournalActivite::where('action', 'role.attribution')->sole()->description);

        $agent->update(['password' => bcrypt('nouveau-mdp')]);
        $modification = JournalActivite::where('action', 'utilisateur.modification')->sole();
        $this->assertSame(['mot de passe'], $modification->details['champs']);
        $this->assertStringNotContainsString('$2y$', json_encode($modification->details), 'Le hachage du mot de passe n’est pas recopié');
    }

    public function test_les_etapes_d_un_dossier_sont_tracees_avec_l_ancien_et_le_nouvel_etat(): void
    {
        $commune = Commune::where('nom', 'Ouagadougou')->firstOrFail();
        $this->actingAs($this->admin);
        $oev = Oev::create([
            'nom' => 'OUEDRAOGO', 'prenom' => 'Awa', 'sexe' => 'F', 'date_naissance' => '2015-03-01', 'statut' => 'vulnerable',
            'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
            'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id,
            'numero_dossier' => Oev::genererNumeroDossier(), 'statut_dossier' => Oev::ETAT_SOUMIS,
        ]);
        $oev->update(['statut_dossier' => Oev::ETAT_VALIDE, 'verifie_at' => now()]);

        $etape = JournalActivite::where('action', 'oev.etat')->sole();
        $this->assertStringContainsString('Soumis au DR → Validé par le DR', $etape->description);
        $this->assertSame(['ancien_etat' => Oev::ETAT_SOUMIS, 'nouvel_etat' => Oev::ETAT_VALIDE], $etape->details);
        // Le champ technique modifié avec l'étape est noté à part
        $this->assertSame(['verifie_at'], JournalActivite::where('action', 'oev.modification')->sole()->details['champs']);
    }

    public function test_l_administrateur_consulte_filtre_et_exporte_le_journal(): void
    {
        JournalActivite::consigner('oev.etat', null, 'Dossier DOS-1 : Soumis → Validé', [], $this->admin);
        JournalActivite::consigner('parrain.creation', null, 'Création du parrain ONG Espoir', [], $this->dr);

        $this->actingAs($this->admin)->get(route('journal.index'))->assertOk()->assertSee('Journal d’audit')->assertSee('ONG Espoir')->assertSee('DOS-1');
        $this->get(route('journal.index', ['module' => 'parrain']))->assertOk()->assertSee('ONG Espoir')->assertDontSee('DOS-1');
        $this->get(route('journal.index', ['utilisateur' => $this->admin->id]))->assertOk()->assertSee('DOS-1')->assertDontSee('ONG Espoir');

        $this->get(route('journal.export'))->assertOk()->assertDownload();
        $this->assertTrue(JournalActivite::where('action', 'extraction.journal')->exists());
    }

    public function test_seuls_les_administrateurs_voient_le_journal(): void
    {
        $this->actingAs($this->dr)->get(route('journal.index'))->assertRedirect();
        $this->actingAs($this->dr)->get(route('journal.export'))->assertRedirect();
    }
}
