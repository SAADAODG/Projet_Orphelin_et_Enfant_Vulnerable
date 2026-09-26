<?php

use App\Http\Controllers\Admin\SignalementController as AdminSignalementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SignalementController;
use App\Http\Controllers\UserController;
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
    Route::get('/dashboard', function () {
        return view('index');
    })->name('dashboard');

    // Module « Liste des signalements »
    Route::get('/signalements', [AdminSignalementController::class, 'index'])->name('admin.signalements.index');
    Route::get('/signalements/non-lus', [AdminSignalementController::class, 'nonLus'])->name('admin.signalements.non-lus');
    Route::get('/signalements/{signalement}', [AdminSignalementController::class, 'show'])->name('admin.signalements.show');
    Route::patch('/signalements/{signalement}/valider', [AdminSignalementController::class, 'valider'])->name('admin.signalements.valider');
    Route::patch('/signalements/{signalement}/rejeter', [AdminSignalementController::class, 'rejeter'])->name('admin.signalements.rejeter');

    Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
    Route::middleware('role:superAdmin|administrateur')->group(function () {
        Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
        Route::post('/roles-permissions', [RolePermissionController::class, 'store'])->name('roles-permissions.store');
        Route::put('/roles-permissions/{role}', [RolePermissionController::class, 'update'])->name('roles-permissions.update');
        Route::delete('/roles-permissions/{role}', [RolePermissionController::class, 'destroy'])->name('roles-permissions.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', [ProfileController::class, 'settings'])->name('settings');
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->name('settings.password.update');
});
