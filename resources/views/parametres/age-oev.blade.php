@extends('layouts.app')
@use('App\Models\ParametreOev')

@section('title', 'Âge des OEV | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Paramètres · fiche OEV</p>
        <h1 class="h3 mb-1">Âge des OEV</h1>
        <p class="text-muted mb-0">Âge jusqu’auquel un enfant a le statut d’OEV. Un enfant en situation de handicap peut être pris en charge plus longtemps.</p>
      </div>
    </div>
  </div>

  <section class="row g-3 mt-0">
    <div class="col-12">
      <form class="panel" method="POST" action="{{ route('parametres.age-oev.update') }}">
        @csrf
        @method('PUT')
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-calendar2-x" aria-hidden="true"></i><span>Âge de sortie</span></h2>
            <p class="text-muted mb-0">
              L’enfant perd le statut d’OEV le jour de cet anniversaire : il ne peut plus être enregistré,
              ni retenu pour une sélection, une extraction ou un appui.
            </p>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label" for="age_limite">Cas général <span class="text-danger">*</span></label>
            <div class="input-group">
              <input class="form-control @error('age_limite') is-invalid @enderror" id="age_limite" name="age_limite" type="number"
                     min="{{ ParametreOev::AGE_MIN }}" max="{{ ParametreOev::AGE_MAX }}" value="{{ old('age_limite', $ageLimite) }}" required>
              <span class="input-group-text">ans</span>
              @error('age_limite')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="age_limite_handicap">Enfant en situation de handicap <span class="text-danger">*</span></label>
            <div class="input-group">
              <input class="form-control @error('age_limite_handicap') is-invalid @enderror" id="age_limite_handicap" name="age_limite_handicap" type="number"
                     min="{{ ParametreOev::AGE_MIN }}" max="{{ ParametreOev::AGE_MAX }}" value="{{ old('age_limite_handicap', $ageLimiteHandicap) }}" required>
              <span class="input-group-text">ans</span>
              @error('age_limite_handicap')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        @if ($ageAtteint)
          <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
            {{ $ageAtteint }} OEV intégré(s) ont atteint l’âge de sortie : ils ne sont plus retenus pour une aide.
            Leur désactivation (motif « Devenu majeur ») se demande depuis leur fiche.
          </div>
        @endif

        <div class="d-flex justify-content-end mt-4">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer</button>
        </div>
      </form>
    </div>
  </section>
</div>
@endsection
