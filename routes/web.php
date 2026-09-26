<?php

use App\Http\Controllers\Admin\SignalementController as AdminSignalementController;
use App\Http\Controllers\SignalementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Pupilles de la Nation (DAPPN)
|--------------------------------------------------------------------------
*/

// --- Routes Publiques ---
Route::get('/', function () {
    return view('public.home');
})->name('public.home');

Route::get('/signaler', [SignalementController::class, 'create'])->name('public.signaler');
Route::post('/signaler', [SignalementController::class, 'store'])->name('public.signaler.store');
Route::get('/signaler/merci', [SignalementController::class, 'merci'])->name('public.signaler.merci');

Route::get('/suivi', [SignalementController::class, 'suivi'])->name('public.suivi');

Route::get('/plainte', function () {
    return view('public.plainte');
})->name('public.plainte');

Route::get('/a-propos', function () {
    return view('public.about');
})->name('public.about');


// --- Routes Administration (Espace Agent & DAPPN) ---
Route::prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        return view('index');
    })->name('dashboard');

    // Module « Liste des signalements »
    Route::get('/signalements', [AdminSignalementController::class, 'index'])->name('admin.signalements.index');
    Route::get('/signalements/non-lus', [AdminSignalementController::class, 'nonLus'])->name('admin.signalements.non-lus');
    Route::get('/signalements/{signalement}', [AdminSignalementController::class, 'show'])->name('admin.signalements.show');
    Route::patch('/signalements/{signalement}/valider', [AdminSignalementController::class, 'valider'])->name('admin.signalements.valider');
    Route::patch('/signalements/{signalement}/rejeter', [AdminSignalementController::class, 'rejeter'])->name('admin.signalements.rejeter');

    Route::get('/users', function () {
        return view('users');
    })->name('users.index');

    Route::get('/users/create', function () {
        return view('add-user');
    })->name('users.create');

    Route::get('/users/{id}', function ($id) {
        return view('user-details', ['userId' => $id]);
    })->name('users.show');

    Route::get('/create-agent', function () {
        return view('create-agent');
    })->name('agents.create');

    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');

    Route::get('/charts', function () {
        return view('charts');
    })->name('charts');

    Route::get('/tables', function () {
        return view('tables');
    })->name('tables');

    Route::get('/forms', function () {
        return view('forms');
    })->name('forms');

    Route::get('/components', function () {
        return view('components');
    })->name('components');

    Route::get('/alerts', function () {
        return view('alerts');
    })->name('alerts');

    Route::get('/modals', function () {
        return view('modals');
    })->name('modals');

    Route::get('/settings', function () {
        return view('settings');
    })->name('settings');

    Route::get('/blank', function () {
        return view('blank');
    })->name('blank');
});


// --- Routes Authentification ---
Route::get('/login', function () {
    return view('login');
})->name('login');

Route::get('/register', function () {
    return view('register');
})->name('register');

Route::get('/forgot-password', function () {
    return view('forgot-password');
})->name('forgot-password');
