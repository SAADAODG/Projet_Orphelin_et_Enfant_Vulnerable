@extends('layouts.app')

@section('title', 'Ajouter un village | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-house-door" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Localité</p>
        <h1 class="h3 mb-1">Ajouter un village / secteur</h1>
        <p class="text-muted mb-0">Rattachez un nouveau village ou secteur à une commune.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.villages.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour aux villages</a>
    </div>
  </div>


  <section class="row g-3">
    <div class="col-12 col-xl-8">
      <form class="panel" method="POST" action="{{ route('localites.villages.store') }}">
        @csrf
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-house-door" aria-hidden="true"></i><span>Informations du village</span></h2>
          </div>
        </div>
        @include('localites.villages._form')
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a class="btn btn-outline-secondary" href="{{ route('localites.villages.index') }}">Annuler</a>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Créer le village</button>
        </div>
      </form>
    </div>
  </section>
</div>
@endsection
