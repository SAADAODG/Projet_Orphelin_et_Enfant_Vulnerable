<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('login', ['ouvrirMotDePasseOublie' => true]);
    }

    public function sendLink(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($validated);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Le lien de réinitialisation a été envoyé par e-mail.')
            : back()->withErrors(['email' => __($status)])->with('ouvrirMotDePasseOublie', true);
    }

    public function resetForm(string $token)
    {
        return view('login', [
            'tokenReinitialisation' => $token,
            'emailReinitialisation' => request('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($validated, function ($user, $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.')
            : redirect()->route('password.reset', [
                'token' => $validated['token'],
                'email' => $validated['email'],
            ])->withErrors(['email' => __($status)]);
    }
}