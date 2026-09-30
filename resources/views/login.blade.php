@extends('layouts.guest')

@section('title', 'Connexion | OEV Burkina Faso')

@php
  // Chaque formulaire a son propre sac d'erreurs : une erreur de connexion n'ouvre aucune modale
  $erreursOubli = $errors->getBag('motDePasseOublie');
  $erreursReinitialisation = $errors->getBag('reinitialisation');
  $modaleAOuvrir = match (true) {
      isset($tokenReinitialisation) => 'nouveauMotDePasseModal',
      ($ouvrirMotDePasseOublie ?? false) || session('ouvrirMotDePasseOublie') || $erreursOubli->any() => 'motDePasseOublieModal',
      default => null,
  };
@endphp

@push('styles')
<style>
  .mdp-champ .btn { border-color: var(--bs-border-color); color: var(--admin-muted, #6b7280); }
  .mdp-champ .btn:hover, .mdp-champ .btn:focus-visible { color: var(--admin-primary, #2563eb); }
</style>
@endpush

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

    @if (session('success'))
      <div class="alert alert-success py-2 mb-3" role="status">
        <small>{{ session('success') }}</small>
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger py-2 mb-3" role="alert">
        <small>{{ $errors->first() }}</small>
      </div>
    @endif

    <div class="mb-3">
      <label class="form-label" for="email">Adresse e-mail</label>
      <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
      <div class="invalid-feedback">Saisissez un e-mail valide.</div>
    </div>

    <div class="mb-3">
      <div class="d-flex justify-content-between">
        <label class="form-label" for="password">Mot de passe</label>
        <button class="btn btn-link btn-sm p-0 small fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#motDePasseOublieModal">Mot de passe oublié ?</button>
      </div>
      <div class="input-group has-validation mdp-champ">
        <input class="form-control" id="password" name="password" type="password" minlength="6" autocomplete="current-password" required>
        <button class="btn btn-outline-secondary" type="button" data-afficher-mdp="password" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe"><i class="bi bi-eye" aria-hidden="true"></i></button>
        <div class="invalid-feedback">Le mot de passe doit contenir au moins 6 caractères.</div>
      </div>
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
      <div class="modal-header">
        <h2 class="modal-title h5" id="motDePasseOublieModalLabel">Mot de passe oublié</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="modal-body">
          <p class="text-muted">Saisissez votre adresse e-mail pour recevoir un lien de réinitialisation.</p>
          <label class="form-label" for="forgot-email">Adresse e-mail</label>
          <input class="form-control @if ($erreursOubli->has('email')) is-invalid @endif" id="forgot-email" name="email" type="email" value="{{ $erreursOubli->any() ? old('email') : '' }}" autocomplete="username" required>
          @if ($erreursOubli->has('email'))
            <div class="invalid-feedback">{{ $erreursOubli->first('email') }}</div>
          @endif
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary" type="submit">Envoyer le lien</button>
        </div>
      </form>
    </div>
  </div>
</div>

@if (isset($tokenReinitialisation))
  <div class="modal fade" id="nouveauMotDePasseModal" tabindex="-1" aria-labelledby="nouveauMotDePasseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5" id="nouveauMotDePasseModalLabel">Nouveau mot de passe</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <form method="POST" action="{{ route('password.update') }}">
          @csrf
          <input type="hidden" name="token" value="{{ $tokenReinitialisation }}">
          <div class="modal-body">
            @if ($erreursReinitialisation->any())
              <div class="alert alert-danger py-2" role="alert"><small>{{ $erreursReinitialisation->first() }}</small></div>
            @endif
            <label class="form-label" for="reset-email">Adresse e-mail</label>
            <input class="form-control mb-3" id="reset-email" name="email" type="email" value="{{ old('email', $emailReinitialisation ?? '') }}" autocomplete="username" required>

            <label class="form-label" for="reset-password">Nouveau mot de passe <span class="text-muted small">(8 caractères minimum)</span></label>
            <div class="input-group mb-3 mdp-champ">
              <input class="form-control" id="reset-password" name="password" type="password" minlength="8" autocomplete="new-password" required>
              <button class="btn btn-outline-secondary" type="button" data-afficher-mdp="reset-password" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </div>

            <label class="form-label" for="reset-password-confirmation">Confirmation</label>
            <div class="input-group mdp-champ">
              <input class="form-control" id="reset-password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
              <button class="btn btn-outline-secondary" type="button" data-afficher-mdp="reset-password-confirmation" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher le mot de passe"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </div>
          </div>
          <div class="modal-footer"><button class="btn btn-primary" type="submit">Réinitialiser</button></div>
        </form>
      </div>
    </div>
  </div>
@endif

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Icône œil : affiche / masque le mot de passe du champ associé
    document.querySelectorAll('[data-afficher-mdp]').forEach(function (bouton) {
      var champ = document.getElementById(bouton.dataset.afficherMdp);
      var icone = bouton.querySelector('i');
      bouton.addEventListener('click', function () {
        var visible = champ.type === 'password';
        champ.type = visible ? 'text' : 'password';
        icone.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
        var libelle = visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe';
        bouton.setAttribute('aria-label', libelle);
        bouton.setAttribute('title', libelle);
        bouton.setAttribute('aria-pressed', visible ? 'true' : 'false');
        champ.focus();
      });
    });

    // Reprend l'e-mail déjà saisi dans le formulaire de connexion
    var modaleOubli = document.getElementById('motDePasseOublieModal');
    modaleOubli.addEventListener('show.bs.modal', function () {
      var emailOubli = document.getElementById('forgot-email');
      if (!emailOubli.value) emailOubli.value = document.getElementById('email').value;
    });
    modaleOubli.addEventListener('shown.bs.modal', function () {
      document.getElementById('forgot-email').focus();
    });

    // Modale ouverte seulement pour le parcours « mot de passe oublié », jamais après une erreur de connexion
    var modalId = @json($modaleAOuvrir);
    if (modalId) {
      bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).show();
    }
  });
</script>
@endpush
@endsection
