<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Mot de passe oublié : demande du lien puis choix du nouveau mot de passe, dans des modales de la page de connexion.
 * Leurs erreurs ont leur propre sac (motDePasseOublie, reinitialisation) pour ne pas se mélanger avec
 * celles de la connexion : une erreur de connexion ne doit pas ouvrir la modale « Mot de passe oublié ».
 */
class PasswordResetController extends Controller
{
    public const SAC_OUBLI = 'motDePasseOublie';
    public const SAC_REINITIALISATION = 'reinitialisation';

    public function requestForm()
    {
        return view('login', ['ouvrirMotDePasseOublie' => true]);
    }

    public function sendLink(Request $request)
    {
        $validated = $request->validateWithBag(self::SAC_OUBLI, ['email' => ['required', 'email']], [
            'email.required' => 'Saisissez votre adresse e-mail.',
            'email.email' => 'Saisissez une adresse e-mail valide.',
        ]);

        $status = Password::sendResetLink($validated);

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->with('ouvrirMotDePasseOublie', true)
                ->withErrors(['email' => 'Un lien vient déjà d’être demandé. Patientez une minute avant de réessayer.'], self::SAC_OUBLI);
        }

        // Même réponse que l'adresse existe ou non : la page ne révèle pas quels e-mails ont un compte
        return back()->with('success', 'Si un compte correspond à cette adresse, un lien de réinitialisation vient de lui être envoyé par e-mail.');
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
        $validated = $request->validateWithBag(self::SAC_REINITIALISATION, [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Saisissez votre adresse e-mail.',
            'email.email' => 'Saisissez une adresse e-mail valide.',
            'password.required' => 'Saisissez le nouveau mot de passe.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation ne correspond pas au mot de passe.',
        ]);

        $status = Password::reset($validated, function ($user, $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.');
        }

        $message = match ($status) {
            Password::INVALID_USER => 'Aucun compte ne correspond à cette adresse e-mail.',
            Password::RESET_THROTTLED => 'Trop de tentatives. Patientez une minute avant de réessayer.',
            default => 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau depuis « Mot de passe oublié ».',
        };

        return redirect()->route('password.reset', ['token' => $validated['token'], 'email' => $validated['email']])
            ->withErrors(['email' => $message], self::SAC_REINITIALISATION);
    }
}
