@extends('layouts.app')

@section('title', 'Ajouter une école | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Paramètres</p>
        <h1 class="h3 mb-1">Ajouter une école</h1>
        <p class="text-muted mb-0">Rattachez une nouvelle école à sa commune : elle sera proposée dans le formulaire OEV.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parametres.ecoles.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour aux écoles</a>
    </div>
  </div>


  <section class="row g-3 mt-0">
    <div class="col-12">
      <form class="panel" method="POST" action="{{ route('parametres.ecoles.store') }}">
        @csrf
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-building" aria-hidden="true"></i><span>Informations de l’école</span></h2>
          </div>
        </div>
        @include('parametres.ecoles._form')
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a class="btn btn-outline-secondary" href="{{ route('parametres.ecoles.index') }}">Annuler</a>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Créer l’école</button>
        </div>
      </form>
    </div>
  </section>
</div>
@endsection
