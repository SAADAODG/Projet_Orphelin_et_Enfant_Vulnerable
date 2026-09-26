<?php

namespace App\Providers;

use App\Models\Signalement;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Pastille rouge du module « Signalements » dans l'espace agent
        View::composer('layouts.app', function ($view) {
            $view->with('signalementsNonLus', Signalement::nonLus()->count());
        });
    }
}
