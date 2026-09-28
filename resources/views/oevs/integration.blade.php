@extends('layouts.app')

@section('title', 'Intégration des OEV | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">OEV · Niveau central</p>
        <h1 class="h3 mb-1">Intégration des OEV</h1>
        <p class="text-muted mb-0">Les dossiers validés par les DR sont intégrés au niveau central : l’enfant devient OEV et reçoit son code OEV.</p>
      </div>
    </div>
  </div>

  @if (session('success')) <div class="alert alert-success mt-3"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> {{ session('success') }}</div> @endif
  @if ($errors->has('circuit')) <div class="alert alert-danger mt-3">{{ $errors->first('circuit') }}</div> @endif

  @include('oevs._liste', [
    'route' => 'oevs.integration',
    'carteTous' => null,
    'messageVide' => match ($filtres['etat']) {
        'valide' => 'Aucun dossier validé en attente d’intégration',
        'complement' => 'Aucun complément en attente auprès des DR',
        default => 'Aucun OEV intégré pour le moment',
    },
  ])
</div>
@endsection
