@extends('layouts.app')

@section('title', 'Modifier ' . $parrain->nom . ' | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage · Parrains</p>
        <h1 class="h3 mb-1">Modifier {{ $parrain->nom }}</h1>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.parrains.show', $parrain) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la fiche</a>
    </div>
  </div>

  <section class="row g-3 mt-1">
    <div class="col-12 col-xl-9">
      <form class="panel" method="POST" action="{{ route('parrainage.parrains.update', $parrain) }}">
        @csrf
        @method('PUT')
        <div class="panel-header">
          <div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-vcard" aria-hidden="true"></i><span>Identité du parrain</span></h2></div>
        </div>
        @include('parrainage.parrains._form')
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a class="btn btn-outline-secondary" href="{{ route('parrainage.parrains.show', $parrain) }}">Annuler</a>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer</button>
        </div>
      </form>
    </div>
  </section>
</div>
@endsection
