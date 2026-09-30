<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Accès refusé : l'application ne montre pas de page 403, elle ramène l'utilisateur à la page
         * précédente avec une alerte (App\Exceptions\RetourApresErreur).
         */
        TestResponse::macro('assertAccesRefuse', function () {
            /** @var TestResponse $this */
            return $this->assertRedirect()
                ->assertSessionHas('warning', 'Vous n’avez pas accès à cette page ou à cette action.');
        });
    }
}
