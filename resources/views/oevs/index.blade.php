@extends('layouts.app')

@section('title', 'Constituer dossier enfant | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Dossiers enfants · Niveau provincial (DP)</p>
        <h1 class="h3 mb-1">Constituer dossier enfant</h1>
        <p class="text-muted mb-0">Le DP constitue le dossier de l’enfant puis le soumet au DR pour vérification de la conformité.</p>
      </div>
    </div>
  </div>

  <div class="mt-3">@include('oevs._onglets')</div>


  @include('oevs._liste', [
    'route' => 'oevs.index',
    'carteTous' => 'Total',
    'messageVide' => 'Aucun dossier dans cette catégorie',
    'lienVide' => auth()->user()->can('constituer dossiers') && ! $filtres['etat']
      ? ['url' => route('oevs.create'), 'label' => 'Constituer un premier dossier', 'icone' => 'bi-plus-lg']
      : null,
  ])
</div>
@endsection
