@extends('layouts.public')

@section('title', 'Plainte | OEV')

@section('content')
<div class="bg-light py-4 border-bottom">
  <div class="container px-3 px-lg-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('public.home') }}" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">Plainte</li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">Déposer une Plainte</h1>
    <p class="text-muted mb-0">Signalez un abus, une maltraitance ou un dysfonctionnement dans la prise en charge d'un enfant.</p>
  </div>
</div>

<section class="public-section">
  <div class="container px-3 px-lg-4">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
          <form action="#" method="POST" class="row g-3">
            @csrf
            <div class="col-12 col-md-6">
              <label for="nom" class="form-label fw-semibold">Nom et prénom(s) <span class="text-muted small">(facultatif)</span></label>
              <input type="text" id="nom" name="nom" class="form-control">
            </div>
            <div class="col-12 col-md-6">
              <label for="telephone" class="form-label fw-semibold">Téléphone</label>
              <input type="tel" id="telephone" name="telephone" class="form-control" placeholder="ex: 70 00 00 00">
            </div>
            <div class="col-12 col-md-6">
              <label for="type" class="form-label fw-semibold">Objet de la plainte</label>
              <select id="type" name="type" class="form-select" required>
                <option value="" selected disabled>Choisir...</option>
                <option value="maltraitance">Maltraitance / violence</option>
                <option value="abandon">Abandon d'enfant</option>
                <option value="exploitation">Exploitation / travail des enfants</option>
                <option value="prise_en_charge">Problème de prise en charge</option>
                <option value="autre">Autre</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label for="localite" class="form-label fw-semibold">Localité</label>
              <input type="text" id="localite" name="localite" class="form-control" placeholder="Province, commune, village..." required>
            </div>
            <div class="col-12">
              <label for="recepisse" class="form-label fw-semibold">N° de signalement concerné <span class="text-muted small">(facultatif)</span></label>
              <input type="text" id="recepisse" name="recepisse" class="form-control" placeholder="ex: OEV-2026-8942">
            </div>
            <div class="col-12">
              <label for="description" class="form-label fw-semibold">Description des faits</label>
              <textarea id="description" name="description" rows="5" class="form-control" required></textarea>
            </div>
            <div class="col-12">
              <label for="pieces" class="form-label fw-semibold">Pièces jointes <span class="text-muted small">(facultatif)</span></label>
              <input type="file" id="pieces" name="pieces[]" class="form-control" multiple>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary btn-lg fw-bold fs-6 shadow-sm">
                <i class="bi bi-send me-1"></i> Envoyer la plainte
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
