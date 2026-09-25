@extends('layouts.app')

@section('title', 'Paramètres | OEV')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Sécurité</p>
        <h1 class="h3 mb-1">Paramètres du profil</h1>
        <p class="text-muted mb-0">Gérez la sécurité de votre compte.</p>
      </div>
    </div>
  </div>

  <section class="row g-3">
    <div class="col-12 col-xl-6">
      <form class="panel needs-validation" method="POST" action="{{ route('settings.password.update') }}" novalidate>
        @csrf
        @method('PUT')
        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>Changer le mot de passe</span></h2>
            <p class="text-muted mb-0">Utilisez un mot de passe d’au moins 8 caractères.</p>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="currentPassword">Mot de passe actuel</label>
          <input class="form-control" id="currentPassword" name="current_password" type="password" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="newPassword">Nouveau mot de passe</label>
          <input class="form-control" id="newPassword" name="password" type="password" minlength="8" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="passwordConfirmation">Confirmer le nouveau mot de passe</label>
          <input class="form-control" id="passwordConfirmation" name="password_confirmation" type="password" minlength="8" required>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Modifier le mot de passe</button>
      </form>
    </div>
    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-person-check" aria-hidden="true"></i><span>Compte connecté</span></h2>
            <p class="text-muted mb-0">Vous êtes connecté avec le compte suivant.</p>
          </div>
        </div>
        <div class="settings-list">
          <label class="settings-row">
            <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span>
          </label>
          <label class="settings-row">
            <span><strong>Rôle(s)</strong><small>{{ auth()->user()->getRoleNames()->join(', ') ?: 'Aucun rôle' }}</small></span>
          </label>
          <label class="settings-row">
            <span><strong>Dernière modification</strong><small>{{ auth()->user()->updated_at?->format('d/m/Y à H:i') }}</small></span>
          </label>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
