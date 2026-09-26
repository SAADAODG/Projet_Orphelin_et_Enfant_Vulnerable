@extends('layouts.guest')

@section('title', 'Connexion | OEV Burkina Faso')

@section('content')
<section class="auth-card">
  <a class="auth-brand" href="{{ route('public.home') }}">
    <span class="brand-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
    <span><strong>OEV</strong><small>Accès à l’espace administratif</small></span>
  </a>

  <div class="auth-visual">
    <img src="{{ asset('assets/images/png/dasher-ui-bootstrap-5.jpg') }}" alt="Interface du tableau de bord OEV">
  </div>

  <form class="needs-validation" method="POST" action="{{ route('login.submit') }}" novalidate>
    @csrf

    <div class="mb-4">
      <p class="eyebrow mb-1">Accès sécurisé</p>
      <h1 class="h3 mb-1">Connexion</h1>
      <p class="text-muted mb-0">Connectez-vous à votre espace de travail</p>
    </div>

    @if ($errors->any())
      <div class="alert alert-danger py-2 mb-3">
        <small>{{ $errors->first() }}</small>
      </div>
    @endif

    <div class="mb-3">
      <label class="form-label" for="email">Adresse e-mail</label>
      <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required>
      <div class="invalid-feedback">Saisissez un e-mail valide.</div>
    </div>

    <div class="mb-3">
      <div class="d-flex justify-content-between">
        <label class="form-label" for="password">Mot de passe</label>
        <button class="btn btn-link btn-sm p-0 small fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#motDePasseOublieModal">Mot de passe oublié ?</button>
      </div>
      <input class="form-control" id="password" name="password" type="password" minlength="6" required>
      <div class="invalid-feedback">Le mot de passe doit contenir au moins 6 caractères.</div>
    </div>

    <div class="form-check mb-4">
      <input class="form-check-input" type="checkbox" id="remember" name="remember">
      <label class="form-check-label" for="remember">Se souvenir de moi</label>
    </div>

    <button class="btn btn-primary w-100" type="submit">
      <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Se connecter
    </button>
  </form>

  <div class="auth-footer">Accès réservé aux utilisateurs autorisés.</div>
</section>

<div class="modal fade" id="motDePasseOublieModal" tabindex="-1" aria-labelledby="motDePasseOublieModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title h5" id="motDePasseOublieModalLabel">Mot de passe oublié</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="modal-body"><p class="text-muted">Saisissez votre adresse e-mail pour recevoir un lien de réinitialisation.</p><label class="form-label" for="forgot-email">Adresse e-mail</label><input class="form-control" id="forgot-email" name="email" type="email" value="{{ old('email') }}" required></div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" type="submit">Envoyer le lien</button></div>
      </form>
    </div>
  </div>
</div>

@if (isset($tokenReinitialisation))
  <div class="modal fade" id="nouveauMotDePasseModal" tabindex="-1" aria-labelledby="nouveauMotDePasseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="nouveauMotDePasseModalLabel">Nouveau mot de passe</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div><form method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $tokenReinitialisation }}"><div class="modal-body"><label class="form-label" for="reset-email">Adresse e-mail</label><input class="form-control mb-3" id="reset-email" name="email" type="email" value="{{ old('email', $emailReinitialisation ?? '') }}" required><label class="form-label" for="reset-password">Nouveau mot de passe</label><input class="form-control mb-3" id="reset-password" name="password" type="password" minlength="8" required><label class="form-label" for="reset-password-confirmation">Confirmation</label><input class="form-control" id="reset-password-confirmation" name="password_confirmation" type="password" minlength="8" required></div><div class="modal-footer"><button class="btn btn-primary" type="submit">Réinitialiser</button></div></form></div></div>
  </div>
@endif

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modalId = @json(isset($tokenReinitialisation) ? 'nouveauMotDePasseModal' : ((session('ouvrirMotDePasseOublie') || $errors->has('email')) ? 'motDePasseOublieModal' : null));
    if (modalId) {
      bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).show();
    }
  });
</script>
@endpush
@endsection
