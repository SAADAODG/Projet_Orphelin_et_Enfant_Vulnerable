<?php

use App\Http\Controllers\Admin\PlainteController as AdminPlainteController;
use App\Http\Controllers\Admin\SignalementController as AdminSignalementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommuneController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExtractionController;
use App\Http\Controllers\OevController;
use App\Http\Controllers\ParametreController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PlainteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\QuickLinkController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SignalementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VillageController;
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
    });
    // Filtrage multicritère des dossiers enfants / OEV et export CSV (limités à la zone de l'utilisateur)
    Route::middleware(['can:voir rapports', 'can:voir OEV'])->group(function () {
        Route::get('/filtrage-extraction', [ExtractionController::class, 'index'])->name('extraction.index');
        Route::get('/filtrage-extraction/export', [ExtractionController::class, 'export'])->name('extraction.export');
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
        Route::post('/quick-links', [QuickLinkController::class, 'store'])->name('quick-links.store');
        Route::put('/quick-links/{quickLink}', [QuickLinkController::class, 'update'])->name('quick-links.update');
        Route::delete('/quick-links/{quickLink}', [QuickLinkController::class, 'destroy'])->name('quick-links.destroy');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', [ProfileController::class, 'settings'])->name('settings');
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->name('settings.password.update');
});
