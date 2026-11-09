<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PlainteController;
use App\Http\Controllers\SignalementController;
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
Route::middleware('auth')->prefix('admin')->group(base_path('routes/admin.php'));
