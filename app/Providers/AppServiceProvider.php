<?php

namespace App\Providers;

use App\Models\Oev;
use App\Models\Plainte;
use App\Models\QuickLink;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\SiteSetting;
use App\Services\Parrainage\SelectionParrainage;
use App\Services\Parrainage\SourceSelection;
use App\Listeners\JournalAudit;
use App\Observers\AuditObserver;
use App\Observers\EtablissementOevObserver;
use App\Observers\ScoreBesoinObserver;
use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Données du module Sélection (listes des sessions) ; établissements, paiement et suivi scolaire pas encore gérés
        $this->app->bind(SourceSelection::class, SelectionParrainage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Dates relatives en français (« il y a 2 heures »)
        Carbon::setLocale('fr');

        // Journal d'audit : enregistrements sensibles, connexions, rôles et permissions
        foreach (AuditObserver::MODELES as $modele) {
            $modele::observe(AuditObserver::class);
        }
        // Score d'urgence enregistré sur la fiche, recalculé en SQL direct (hors journal d'audit)
        Oev::observe(ScoreBesoinObserver::class);
        // Établissement payé par les sessions de parrainage : suit l'école ou le centre de la fiche tant qu'il n'est pas payé
        Oev::observe(EtablissementOevObserver::class);
        config(['permission.events_enabled' => true]);
        Event::listen(Login::class, [JournalAudit::class, 'connexion']);
        Event::listen(Logout::class, [JournalAudit::class, 'deconnexion']);
        Event::listen(Failed::class, [JournalAudit::class, 'echec']);
        Event::listen(RoleAttachedEvent::class, [JournalAudit::class, 'roleAttribue']);
        Event::listen(RoleDetachedEvent::class, [JournalAudit::class, 'roleRetire']);
        Event::listen(PermissionAttachedEvent::class, [JournalAudit::class, 'permissionAttribuee']);
        Event::listen(PermissionDetachedEvent::class, [JournalAudit::class, 'permissionRetiree']);

        // Pastilles rouges des modules « Signalements » (zone de l'utilisateur) et « Gestion de plainte »,
        // calculées seulement pour les modules auxquels l'utilisateur a accès
        View::composer('layouts.app', function ($view) {
            /** @var \App\Models\User|null $utilisateur */
            $utilisateur = Auth::user();
            $view->with('signalementsNonLus', $utilisateur?->can('voir signalements') ? Signalement::nonLus()->dansLePerimetreDe($utilisateur)->count() : 0);
            $view->with('plaintesNonLues', $utilisateur?->can('voir plaintes') ? Plainte::nonLues()->count() : 0);
        });

        // Identité du site, liens rapides et services (Paramètres généraux) : partagés avec les
        // layouts ET les pages qui les étendent, car @extends exécute le contenu de la page avant
        // d'assembler le layout (un composer posé sur le seul layout serait invisible dans les
        // @section('title', ...) des pages).
        View::composer([
            'layouts.*', 'public.*', 'admin.*', 'oevs.*', 'localites.*', 'parametres.*', 'parrainage.*',
            'index', 'login', 'profile', 'settings', 'users', 'roles-permissions', 'errors.*',
        ], function ($view) {
            // Lu une seule fois par requête : le composer s'applique aussi à chaque vue partielle
            // incluse (champs du formulaire OEV…), qui relançait sinon les trois requêtes à chaque fois.
            $attributs = request()->attributes;
            if (! $attributs->has('identiteSite')) {
                $attributs->set('identiteSite', self::identiteSite());
            }

            $view->with($attributs->get('identiteSite'));
        });
    }

    /** @return array{siteSetting: SiteSetting, quickLinks: \Illuminate\Support\Collection, services: \Illuminate\Support\Collection} */
    private static function identiteSite(): array
    {
        // Repli défensif : une page d'erreur (dont 500) doit pouvoir s'afficher même si la
        // base de données est indisponible ou pas encore migrée.
        try {
            return [
                'siteSetting' => SiteSetting::current(),
                'quickLinks' => QuickLink::orderBy('ordre')->get(),
                'services' => Service::orderBy('ordre')->get(),
            ];
        } catch (\Throwable $e) {
            return [
                'siteSetting' => new SiteSetting([
                    'nom_site' => 'Programme OEV',
                    'structure_nom' => 'DGFE',
                    'ministere_tutelle' => 'Ministère de la Famille et de la Solidarité',
                ]),
                'quickLinks' => collect(),
                'services' => collect(),
            ];
        }
    }
}
