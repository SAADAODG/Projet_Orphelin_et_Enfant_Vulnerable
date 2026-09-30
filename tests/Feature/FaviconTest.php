<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_armoiries_servent_d_icone_d_onglet_sur_toutes_les_pages(): void
    {
        $icone = 'assets/images/icones/favicon-32.png';

        $this->get(route('public.home'))->assertOk()->assertSee($icone, false);
        $this->get(route('login'))->assertOk()->assertSee($icone, false);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee($icone, false);

        $this->assertFileExists(public_path($icone));
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')), 'favicon.ico ne doit pas être vide');
    }
}
