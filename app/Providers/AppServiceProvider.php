<?php

namespace App\Providers;

use App\Models\Plainte;
use App\Models\Signalement;
use Carbon\Carbon;
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

        // Dates relatives en français (« il y a 2 heures »)
        Carbon::setLocale('fr');

        // Pastilles rouges des modules « Signalements » et « Gestion de plainte » dans l'espace agent
        View::composer('layouts.app', function ($view) {
            $view->with('signalementsNonLus', Signalement::nonLus()->count());
            $view->with('plaintesNonLues', Plainte::nonLues()->count());
        });
    }
}
