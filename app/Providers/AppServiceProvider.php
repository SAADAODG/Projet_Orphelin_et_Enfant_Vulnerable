<?php

namespace App\Providers;

use App\Models\Plainte;
use App\Models\QuickLink;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\SiteSetting;
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

        // Identité du site, liens rapides et services (Paramètres généraux) : partagés avec les
        // layouts ET les pages qui les étendent, car @extends exécute le contenu de la page avant
        // d'assembler le layout (un composer posé sur le seul layout serait invisible dans les
        // @section('title', ...) des pages).
        View::composer([
            'layouts.*', 'public.*', 'admin.*', 'oevs.*', 'localites.*', 'parametres.*',
            'index', 'login', 'profile', 'settings', 'users', 'roles-permissions', 'errors.*',
        ], function ($view) {
            // Repli défensif : une page d'erreur (dont 500) doit pouvoir s'afficher même si la
            // base de données est indisponible ou pas encore migrée.
            try {
                $siteSetting = SiteSetting::current();
                $quickLinks = QuickLink::orderBy('ordre')->get();
                $services = Service::orderBy('ordre')->get();
            } catch (\Throwable $e) {
                $siteSetting = new SiteSetting([
                    'nom_site' => 'Programme OEV',
                    'structure_nom' => 'DGFE',
                    'ministere_tutelle' => 'Ministère de la Famille et de la Solidarité',
                ]);
                $quickLinks = collect();
                $services = collect();
            }

            $view->with('siteSetting', $siteSetting);
            $view->with('quickLinks', $quickLinks);
            $view->with('services', $services);
        });
    }
}
