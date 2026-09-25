@extends('layouts.app')

@section('title', 'Mon profil | OEV')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Compte</p>
        <h1 class="h3 mb-1">Mon profil</h1>
        <p class="text-muted mb-0">Consultez et modifiez vos informations personnelles.</p>
      </div>
    </div>
  </div>

  <section class="row g-3">
    <div class="col-12 col-xl-4">
      <div class="panel h-100 text-center profile-card">
        <div class="profile-cover">
          <img src="{{ asset('assets/images/png/dasher-ui-bootstrap-5.jpg') }}" alt="adminHMD dashboard preview">
        </div>
        <img class="avatar-img avatar-xl profile-photo" src="{{ asset('assets/images/avatar/avatar.jpg') }}" alt="{{ $user->name }}">
        <h2 class="h5 mt-3 mb-1">{{ $user->name }}</h2>
        <p class="text-muted mb-3">{{ $user->getRoleNames()->join(', ') ?: 'Utilisateur' }}</p>
        <div class="d-flex justify-content-center gap-2">
          <span class="badge text-bg-primary">Admin</span>
          <span class="badge text-bg-success">Verified</span>
        </div>
        <div class="info-list mt-4 text-start">
          <div><span>E-mail</span><strong>{{ $user->email }}</strong></div>
          <div><span>Rôle</span><strong>{{ $user->getRoleNames()->join(', ') ?: 'Aucun rôle' }}</strong></div>
          <div><span>Membre depuis</span><strong>{{ $user->created_at?->format('d/m/Y') }}</strong></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-8">
      <form class="panel needs-validation" method="POST" action="{{ route('profile.update') }}" novalidate>
        @csrf
        @method('PUT')
        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Informations personnelles</span></h2>
            <p class="text-muted mb-0">Mettez à jour votre nom et votre adresse e-mail.</p>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="profileName">Nom complet</label>
            <input class="form-control" id="profileName" name="name" type="text" value="{{ old('name', $user->name) }}" required>
            <div class="invalid-feedback">Le nom est obligatoire.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="profileEmail">Adresse e-mail</label>
            <input class="form-control" id="profileEmail" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            <div class="invalid-feedback">Saisissez une adresse valide.</div>
          </div>
        </div>
        <div class="d-flex justify-content-end mt-4">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Enregistrer</button>
        </div>
      </form>
    </div>
  </section>
</div>
@endsection
