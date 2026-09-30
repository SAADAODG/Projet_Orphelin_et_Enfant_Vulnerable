@extends('layouts.app')

@section('title', 'Validation des dossiers | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-patch-check" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Dossiers enfants · Niveau régional (DR)</p>
        <h1 class="h3 mb-1">Validation des dossiers</h1>
        <p class="text-muted mb-0">Le DR vérifie la conformité des dossiers soumis par les DP : conforme (validé et transmis au niveau central) ou non conforme (renvoyé au DP avec un motif).</p>
      </div>
    </div>
  </div>

  @if (session('success')) <div class="alert alert-success mt-3"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> {{ session('success') }}</div> @endif
  @if ($errors->has('circuit')) <div class="alert alert-danger mt-3">{{ $errors->first('circuit') }}</div> @endif

  @if (($compteurs['complement'] ?? 0) > 0 && $filtres['etat'] !== 'complement')
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
      <span><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> <strong>{{ $compteurs['complement'] }} dossier(s)</strong> renvoyé(s) par le niveau central avec une demande de complément.</span>
      <a class="btn btn-sm btn-warning" href="{{ route('oevs.validation', ['etat' => 'complement']) }}">Voir les compléments demandés</a>
    </div>
  @endif

  @if ($filtres['etat'] === 'soumis' && ($compteurs['soumis'] ?? 0) === 0 && $enConstitution > 0)
    <div class="alert alert-info mt-3">
      <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
      Aucun dossier n’est en attente de vérification. <strong>{{ $enConstitution }} dossier(s)</strong> sont en cours de constitution chez les DP :
      ils apparaîtront ici dès que le DP cliquera sur « Soumettre au DR ».
    </div>
  @endif

  @include('oevs._liste', [
    'route' => 'oevs.validation',
    'carteTous' => null,
    'messageVide' => match ($filtres['etat']) {
        'soumis' => 'Aucun dossier en attente de vérification',
        'complement' => 'Aucun complément demandé par le niveau central',
        default => 'Aucun dossier dans cette catégorie',
    },
  ])
</div>
@endsection
