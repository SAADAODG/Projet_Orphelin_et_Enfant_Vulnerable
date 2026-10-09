@extends('layouts.app')

@section('title', 'Modifier la session ' . $session->reference() . ' | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-calendar2-range" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage · Sessions</p>
        <h1 class="h3 mb-1">Modifier la session {{ $session->reference() }}</h1>
        <p class="text-muted mb-0">{{ $session->description }}</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.sessions.show', $session) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la session</a>
    </div>
  </div>

  <section class="row g-3 mt-1">
    <div class="col-12 col-xl-9">
      <form class="panel" method="POST" action="{{ route('parrainage.sessions.update', $session) }}">
        @csrf
        @method('PUT')
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Informations de la session</span></h2>
          </div>
        </div>
        @include('parrainage.sessions._form')
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a class="btn btn-outline-secondary" href="{{ route('parrainage.sessions.show', $session) }}">Annuler</a>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer</button>
        </div>
      </form>
    </div>
  </section>
</div>
@endsection
