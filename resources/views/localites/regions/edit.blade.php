@extends('layouts.app')

@section('title', 'Modifier une région | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-map" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Localité</p>
        <h1 class="h3 mb-1">Modifier « {{ $region->nom }} »</h1>
        <p class="text-muted mb-0">Mettez à jour les informations de la région.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.regions.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour aux régions</a>
    </div>
  </div>

  @include('partials.flash')

  <section class="row g-3">
    <div class="col-12 col-xl-8">
      <form class="panel" method="POST" action="{{ route('localites.regions.update', $region) }}">
        @csrf
        @method('PUT')
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-map" aria-hidden="true"></i><span>Informations de la région</span></h2>
          </div>
        </div>
        @include('localites.regions._form')
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a class="btn btn-outline-secondary" href="{{ route('localites.regions.index') }}">Annuler</a>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer les modifications</button>
        </div>
      </form>
    </div>
  </section>
</div>
@endsection
