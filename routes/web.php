<?php

use App\Http\Controllers\Admin\PlainteController as AdminPlainteController;
use App\Http\Controllers\Admin\SignalementController as AdminSignalementController;
use App\Http\Controllers\AppuiPartenaireController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommuneController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesactivationOevController;
use App\Http\Controllers\EcoleController;
use App\Http\Controllers\ExtractionController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ListeReferenceController;
use App\Http\Controllers\NatureAppuiController;
use App\Http\Controllers\OevController;
use App\Http\Controllers\AgeOevController;
use App\Http\Controllers\PaiementSessionController;
use App\Http\Controllers\ParametreController;
use App\Http\Controllers\ParrainController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PilotageParrainageController;
use App\Http\Controllers\PlainteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\QuickLinkController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SelectionParrainageController;
use App\Http\Controllers\SessionParrainageController;
use App\Http\Controllers\SuiviParrainageController;
use App\Http\Controllers\SignalementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VillageController;
use App\Models\ListeReference;
use App\Models\Province;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Pupilles de la Nation (DAPPN)
|--------------------------------------------------------------------------
*/

// --- Routes Publiques ---
Route::get('/', function () {
    return view('public.home', ['nombreProvinces' => Province::count()]);
})->name('public.home');

Route::get('/signaler', [SignalementController::class, 'create'])->name('public.signaler');
Route::post('/signaler', [SignalementController::class, 'store'])->name('public.signaler.store');
Route::get('/signaler/merci', [SignalementController::class, 'merci'])->name('public.signaler.merci');
Route::get('/signaler/recepisse/{recepisse}', [SignalementController::class, 'recepisse'])->name('public.signaler.recepisse');

Route::get('/suivi', [SignalementController::class, 'suivi'])->name('public.suivi');

Route::get('/plainte', [PlainteController::class, 'create'])->name('public.plainte');
Route::post('/plainte', [PlainteController::class, 'store'])->name('public.plainte.store');
Route::get('/plainte/merci', [PlainteController::class, 'merci'])->name('public.plainte.merci');

Route::get('/a-propos', function () {
    return view('public.about');
})->name('public.about');

// --- Routes Authentification ---
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('login');
    })->name('login');

    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

// --- Routes Administration (Espace Agent & DAPPN) ---
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Module « Signalements » : le DP traite ceux de sa province, le DR consulte ceux de sa région
    Route::middleware('can:voir signalements')->group(function () {
        Route::get('/signalements', [AdminSignalementController::class, 'index'])->name('admin.signalements.index');
        Route::get('/signalements/non-lus', [AdminSignalementController::class, 'nonLus'])->name('admin.signalements.non-lus');
        Route::get('/signalements/{signalement}', [AdminSignalementController::class, 'show'])->name('admin.signalements.show');
    });
    Route::middleware('can:traiter signalements')->group(function () {
        Route::patch('/signalements/{signalement}/valider', [AdminSignalementController::class, 'valider'])->name('admin.signalements.valider');
        Route::patch('/signalements/{signalement}/rejeter', [AdminSignalementController::class, 'rejeter'])->name('admin.signalements.rejeter');
        Route::patch('/signalements/{signalement}/cloturer', [AdminSignalementController::class, 'cloturer'])->name('admin.signalements.cloturer');
    });

    // Module « Gestion de plainte » : niveau central
    Route::middleware('can:voir plaintes')->group(function () {
        Route::get('/plaintes', [AdminPlainteController::class, 'index'])->name('admin.plaintes.index');
        Route::get('/plaintes/{plainte}', [AdminPlainteController::class, 'show'])->name('admin.plaintes.show');
    });
    Route::patch('/plaintes/{plainte}/statut', [AdminPlainteController::class, 'statut'])->middleware('can:traiter plaintes')->name('admin.plaintes.statut');

    // Gestion des utilisateurs
    Route::get('/users', [UserController::class, 'index'])->middleware('can:voir utilisateurs')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:créer utilisateurs')->name('users.store');
    Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->middleware('can:modifier utilisateurs')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:supprimer utilisateurs')->name('users.destroy');
    Route::middleware('role:superAdmin|administrateur')->group(function () {
        Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
        Route::post('/roles-permissions', [RolePermissionController::class, 'store'])->name('roles-permissions.store');
        Route::put('/roles-permissions/{role}', [RolePermissionController::class, 'update'])->name('roles-permissions.update');
        Route::delete('/roles-permissions/{role}', [RolePermissionController::class, 'destroy'])->name('roles-permissions.destroy');
    });

    // --- Dossiers enfants / OEV : DP (constituer) → DR (valider) → niveau central (intégrer) ---
    Route::middleware('can:constituer dossiers')->group(function () {
        Route::get('/oevs', [OevController::class, 'index'])->name('oevs.index');
        Route::get('/oevs/create', [OevController::class, 'create'])->name('oevs.create');
        Route::post('/oevs', [OevController::class, 'store'])->name('oevs.store');
        Route::delete('/oevs/{oev}', [OevController::class, 'destroy'])->name('oevs.destroy');
        Route::post('/oevs/{oev}/soumettre', [OevController::class, 'soumettre'])->name('oevs.soumettre');
    });
    // Modification : DP pendant la constitution, DR sur demande de complément (contrôlé dans le contrôleur)
    Route::middleware('can:voir OEV')->group(function () {
        Route::get('/oevs/{oev}/edit', [OevController::class, 'edit'])->name('oevs.edit');
        Route::put('/oevs/{oev}', [OevController::class, 'update'])->name('oevs.update');
        Route::delete('/oevs/{oev}/documents/{document}', [OevController::class, 'destroyDocument'])->name('oevs.documents.destroy');
        // Rejet définitif : DR ou niveau central selon l'étape (contrôlé dans le contrôleur)
        Route::post('/oevs/{oev}/rejeter', [OevController::class, 'rejeter'])->name('oevs.rejeter');
    });
    Route::middleware('can:valider dossiers')->group(function () {
        Route::get('/validation-dossiers', [OevController::class, 'validation'])->name('oevs.validation');
        Route::post('/oevs/{oev}/conforme', [OevController::class, 'conforme'])->name('oevs.conforme');
        Route::post('/oevs/{oev}/non-conforme', [OevController::class, 'nonConforme'])->name('oevs.non-conforme');
    });
    Route::middleware('can:intégrer OEV')->group(function () {
        Route::get('/integration-oev', [OevController::class, 'integration'])->name('oevs.integration');
        Route::post('/oevs/{oev}/integrer', [OevController::class, 'integrer'])->name('oevs.integrer');
        Route::post('/oevs/{oev}/complement', [OevController::class, 'demanderComplement'])->name('oevs.complement');
    });
    Route::middleware('can:voir OEV')->group(function () {
        Route::get('/liste-oev', [OevController::class, 'liste'])->name('oevs.liste');
        Route::get('/oevs/{oev}', [OevController::class, 'show'])->name('oevs.show');
        Route::get('/oevs/{oev}/documents/{document}', [OevController::class, 'showDocument'])->name('oevs.documents.show');
        // Désactivation d'un OEV intégré : demandée par le DP, décidée par le niveau central (contrôlé dans le contrôleur)
        // OEV sortants : désactivés (décès, majorité…) et demandes de désactivation en attente
        Route::get('/oevs-sortants', [DesactivationOevController::class, 'index'])->name('oevs.sortants');
        Route::post('/oevs/{oev}/desactiver', [DesactivationOevController::class, 'desactiver'])->name('oevs.desactiver');
        Route::post('/oevs/{oev}/desactivation/decision', [DesactivationOevController::class, 'decider'])->name('oevs.desactivation.decider');
        Route::post('/oevs/{oev}/reactiver', [DesactivationOevController::class, 'reactiver'])->name('oevs.reactiver');
    });
    // Filtrage multicritère des dossiers enfants / OEV et export CSV (limités à la zone de l'utilisateur)
    Route::middleware(['can:voir rapports', 'can:voir OEV'])->group(function () {
        Route::get('/filtrage-extraction', [ExtractionController::class, 'index'])->name('extraction.index');
        Route::get('/filtrage-extraction/export', [ExtractionController::class, 'export'])->name('extraction.export');
    });

    // --- Parrainage : sessions ouvertes par le niveau central, consultées par les DR et DP ---
    Route::prefix('parrainage')->name('parrainage.')->group(function () {
        Route::middleware('can:gérer sessions parrainage')->group(function () {
            Route::get('/sessions/create', [SessionParrainageController::class, 'create'])->name('sessions.create');
            Route::post('/sessions', [SessionParrainageController::class, 'store'])->name('sessions.store');
            Route::get('/sessions/{session}/edit', [SessionParrainageController::class, 'edit'])->name('sessions.edit');
            Route::put('/sessions/{session}', [SessionParrainageController::class, 'update'])->name('sessions.update');
            Route::put('/sessions/{session}/quotas', [SessionParrainageController::class, 'updateQuotas'])->name('sessions.quotas');
            // Sélection des OEV de la session : ajout, frais, validation de la liste définitive ; réception des fonds
            Route::delete('/sessions/{session}', [SessionParrainageController::class, 'destroy'])->name('sessions.destroy');
            Route::post('/sessions/{session}/selection', [SelectionParrainageController::class, 'ajouter'])->name('sessions.selection.ajouter');
            Route::put('/sessions/{session}/selection/estimations', [SelectionParrainageController::class, 'estimations'])->name('sessions.selection.estimations');
            Route::patch('/sessions/{session}/selection/{ligne}', [SelectionParrainageController::class, 'modifier'])->name('sessions.selection.modifier');
            Route::delete('/sessions/{session}/selection/{ligne}', [SelectionParrainageController::class, 'retirer'])->name('sessions.selection.retirer');
            Route::post('/sessions/{session}/selection/valider', [SelectionParrainageController::class, 'valider'])->name('sessions.selection.valider');
            Route::post('/sessions/{session}/paiements', [PaiementSessionController::class, 'store'])->name('sessions.paiements.store');
            Route::delete('/sessions/{session}/paiements/{paiement}', [PaiementSessionController::class, 'destroy'])->name('sessions.paiements.destroy');
        });
        // Suivi de fin d'année des parrainés : le DP pour sa province, le niveau central pour tout le pays
        Route::put('/suivis/{ligne}', [SuiviParrainageController::class, 'enregistrer'])
            ->middleware('can:suivre parrainages')->name('suivis.enregistrer');
        // Répertoire des parrains : géré par le niveau central
        Route::middleware('can:gérer parrains')->group(function () {
            Route::get('/parrains/create', [ParrainController::class, 'create'])->name('parrains.create');
            Route::post('/parrains', [ParrainController::class, 'store'])->name('parrains.store');
            Route::get('/parrains/{parrain}/edit', [ParrainController::class, 'edit'])->name('parrains.edit');
            Route::put('/parrains/{parrain}', [ParrainController::class, 'update'])->name('parrains.update');
            Route::delete('/parrains/{parrain}', [ParrainController::class, 'destroy'])->name('parrains.destroy');
            Route::post('/parrains/{parrain}/engagements', [ParrainController::class, 'storeEngagement'])->name('parrains.engagements.store');
            Route::delete('/parrains/{parrain}/engagements/{engagement}', [ParrainController::class, 'destroyEngagement'])->name('parrains.engagements.destroy');
        });
        // Appuis : le DP pour les OEV de sa province, le niveau central pour tout le pays
        Route::middleware('can:enregistrer appuis')->group(function () {
            Route::get('/appuis/create', [AppuiPartenaireController::class, 'create'])->name('appuis.create');
            Route::post('/appuis', [AppuiPartenaireController::class, 'store'])->name('appuis.store');
            Route::get('/appuis/recherche-oev', [AppuiPartenaireController::class, 'rechercheOev'])->name('appuis.recherche-oev');
            Route::get('/appuis/modele', [AppuiPartenaireController::class, 'modele'])->name('appuis.modele');
            Route::get('/appuis/import', [AppuiPartenaireController::class, 'importForm'])->name('appuis.import');
            Route::post('/appuis/import', [AppuiPartenaireController::class, 'import'])->name('appuis.import.store');
            Route::get('/appuis/{appui}/edit', [AppuiPartenaireController::class, 'edit'])->name('appuis.edit');
            Route::put('/appuis/{appui}', [AppuiPartenaireController::class, 'update'])->name('appuis.update');
        });
        Route::middleware('can:voir parrainage')->group(function () {
            Route::get('/parrains', [ParrainController::class, 'index'])->name('parrains.index');
            Route::get('/parrains/{parrain}', [ParrainController::class, 'show'])->name('parrains.show');
            Route::get('/parrains/{parrain}/engagements/{engagement}/convention', [ParrainController::class, 'convention'])->name('parrains.engagements.convention');
            Route::get('/appuis', [AppuiPartenaireController::class, 'index'])->name('appuis.index');
            // Pilotage : tableau de bord et extractions, dans la zone de l'utilisateur (droits fins contrôlés dans le contrôleur)
            Route::get('/pilotage', [PilotageParrainageController::class, 'tableauDeBord'])->name('pilotage.index');
            Route::get('/pilotage/extractions', [PilotageParrainageController::class, 'extractions'])->name('pilotage.extractions');
            Route::get('/pilotage/extractions/paiement', [PilotageParrainageController::class, 'paiement'])->name('pilotage.paiement');
            Route::get('/pilotage/extractions/parrain', [PilotageParrainageController::class, 'listeParrain'])->name('pilotage.liste-parrain');
            Route::get('/suivis', [SuiviParrainageController::class, 'index'])->name('suivis.index');
            Route::get('/sessions', [SessionParrainageController::class, 'index'])->name('sessions.index');
            Route::get('/sessions/{session}', [SessionParrainageController::class, 'show'])->name('sessions.show');
            Route::get('/sessions/{session}/selection', [SelectionParrainageController::class, 'index'])->name('sessions.selection');
            // Paiement d'une session validée : établissements et régions (niveau central, DR pour leur région)
            Route::get('/sessions/{session}/paiements', [PaiementSessionController::class, 'index'])->name('sessions.paiements');
            Route::get('/sessions/{session}/paiements/export', [PaiementSessionController::class, 'export'])->name('sessions.paiements.export');
            // Droits contrôlés par le service : avancer (gestionnaires), revenir en arrière (administrateurs)
            Route::post('/sessions/{session}/etat', [SessionParrainageController::class, 'changerEtat'])->name('sessions.etat');
        });
    });

    // --- Paramétrage : localités (Régions > Provinces > Communes > Villages) et paramètres généraux du site ---
    Route::middleware('can:gérer paramètres')->group(function () {
        Route::prefix('localites')->name('localites.')->group(function () {
            Route::resource('regions', RegionController::class)->except('show');
            Route::resource('provinces', ProvinceController::class)->except('show');
            Route::resource('communes', CommuneController::class)->except('show');
            Route::resource('villages', VillageController::class)->except('show');
        });

        Route::get('/parametres', [ParametreController::class, 'edit'])->name('parametres.edit');
        Route::put('/parametres', [ParametreController::class, 'update'])->name('parametres.update');
        // Paramètres > Écoles : référentiel proposé dans le formulaire OEV (établissement fréquenté)
        Route::prefix('parametres')->name('parametres.')->group(function () {
            Route::post('/ecoles/import', [EcoleController::class, 'import'])->name('ecoles.import');
            Route::resource('ecoles', EcoleController::class)->except('show');
            // Listes de choix de la fiche OEV : groupes de population, qui s'occupe de l'enfant, conditions de vie, vulnérabilités
            Route::prefix('listes/{liste}')->name('listes.')->whereIn('liste', array_keys(ListeReference::listes()))->group(function () {
                Route::get('/', [ListeReferenceController::class, 'index'])->name('index');
                Route::post('/', [ListeReferenceController::class, 'store'])->name('store');
                Route::put('/{element}', [ListeReferenceController::class, 'update'])->name('update');
                Route::delete('/{element}', [ListeReferenceController::class, 'destroy'])->name('destroy');
            });
            // Âge de sortie des OEV (cas général, enfant en situation de handicap)
            Route::get('/age-oev', [AgeOevController::class, 'edit'])->name('age-oev.edit');
            Route::put('/age-oev', [AgeOevController::class, 'update'])->name('age-oev.update');
            // Natures d'appui du parrainage (appuis des partenaires, engagements, extractions)
            Route::resource('natures-appui', NatureAppuiController::class)->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['natures-appui' => 'nature']);
        });
        Route::post('/quick-links', [QuickLinkController::class, 'store'])->name('quick-links.store');
        Route::put('/quick-links/{quickLink}', [QuickLinkController::class, 'update'])->name('quick-links.update');
        Route::delete('/quick-links/{quickLink}', [QuickLinkController::class, 'destroy'])->name('quick-links.destroy');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    // Journal d'audit : consultation et export (administrateurs)
    Route::middleware('can:voir journal')->group(function () {
        Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
        Route::get('/journal/export', [JournalController::class, 'export'])->name('journal.export');
    });

    // Villages d'une commune, chargés à la demande par les listes de localités (tout agent connecté)
    Route::get('/localites/communes/{commune}/villages', [VillageController::class, 'parCommune'])->name('localites.villages-commune');
    // Écoles d'une commune, chargées à la demande par le formulaire OEV (tout agent connecté)
    Route::get('/localites/communes/{commune}/ecoles', [EcoleController::class, 'parCommune'])->name('localites.ecoles-commune');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', [ProfileController::class, 'settings'])->name('settings');
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->name('settings.password.update');
});
