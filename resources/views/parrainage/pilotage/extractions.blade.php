@extends('layouts.app')
@use('App\Models\Oev')
@use('App\Models\SessionParrainage')

@section('title', 'Extractions du parrainage | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage</p>
        <h1 class="h3 mb-1">Pilotage</h1>
        <p class="text-muted mb-0">Extractions en Excel ou en PDF, limitées à votre zone. Chaque extraction est enregistrée dans le journal.</p>
      </div>
    </div>
  </div>

  <div class="mt-3">@include('parrainage.pilotage._onglets')</div>

  {{-- Bascule entre les deux extractions --}}
  <div class="btn-group mt-3" role="group" aria-label="Type d’extraction">
    <a class="btn btn-sm {{ $vue === 'parrain' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('parrainage.pilotage.extractions') }}" @if ($vue === 'parrain') aria-current="page" @endif>
      <i class="bi bi-person-heart" aria-hidden="true"></i> Liste pour un parrain
    </a>
    @if ($peutPaiement)
      <a class="btn btn-sm {{ $vue === 'paiement' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('parrainage.pilotage.extractions', ['vue' => 'paiement']) }}" @if ($vue === 'paiement') aria-current="page" @endif>
        <i class="bi bi-bank" aria-hidden="true"></i> Liste pour paiement
      </a>
    @endif
  </div>

  @if ($vue === 'paiement')
    <form class="panel mt-3" method="GET" action="{{ route('parrainage.pilotage.paiement') }}">
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title"><i class="bi bi-bank" aria-hidden="true"></i><span>Liste pour paiement</span></h2>
          <p class="text-muted mb-0">Pour les services financiers : le paiement se fait hors de la plateforme. Feuille « Par établissement » (avec RIB) et feuille « Nominatif ».</p>
        </div>
      </div>
      @unless ($disponible)
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i> Données non disponibles : la liste définitive et les établissements viendront du module Sélection. L’extraction produit pour l’instant un fichier sans ligne.</div>
      @endunless
      @if ($errors->any())
        <div class="alert alert-danger small">{{ $errors->first() }}</div>
      @endif
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="session_id">Session <span class="text-danger">*</span></label>
          <select class="form-select" id="session_id" name="session_id" required>
            <option value="">Choisir une session validée…</option>
            @foreach ($sessionsPaiement as $session)
              <option value="{{ $session->id }}" @selected((string) old('session_id') === (string) $session->id)>{{ $session->reference() }} — {{ $session->description }} ({{ $session->libelleEtat() }})</option>
            @endforeach
          </select>
          @if ($sessionsPaiement->isEmpty())<div class="form-text">Aucune session validée pour l’instant.</div>@endif
        </div>
        <div class="col-md-3">
          <label class="form-label" for="type_appui_paiement">Type d’appui</label>
          <select class="form-select" id="type_appui_paiement" name="type_appui">
            <option value="">Tous</option>
            @foreach (SessionParrainage::TYPES_APPUI as $cle => $libelle)
              <option value="{{ $cle }}">{{ $libelle }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="etablissement_id">Établissement</label>
          <select class="form-select" id="etablissement_id" name="etablissement_id" @disabled($etablissements->isEmpty())>
            <option value="">{{ $etablissements->isEmpty() ? 'Non disponible' : 'Tous' }}</option>
            @foreach ($etablissements as $etablissement)
              <option value="{{ $etablissement['id'] }}">{{ $etablissement['nom'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          @include('partials.localite-selects', [
            'localites' => $localites,
            'requis' => false,
            'colonnes' => ['region_id' => 'col-md-4', 'province_id' => 'col-md-4', 'commune_id' => 'col-md-4'],
          ])
        </div>
      </div>
      <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-primary" type="submit" name="format" value="pdf"><i class="bi bi-filetype-pdf" aria-hidden="true"></i> PDF</button>
        <button class="btn btn-primary" type="submit" name="format" value="xlsx"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel</button>
      </div>
    </form>
  @else
    <form class="panel mt-3" method="GET" action="{{ route('parrainage.pilotage.extractions') }}">
      <input type="hidden" name="filtre" value="1">
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title"><i class="bi bi-person-heart" aria-hidden="true"></i><span>Liste pour un parrain</span></h2>
          <p class="text-muted mb-0">Proposez des OEV à un partenaire. La liste est préfiltrée selon sa zone et ses natures d’appui ; ajustez les filtres si besoin.</p>
        </div>
      </div>
      @if ($errors->any())
        <div class="alert alert-danger small">{{ $errors->first() }}</div>
      @endif

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="parrain_id">Parrain <span class="text-danger">*</span></label>
          <select class="form-select" id="parrain_id" name="parrain_id" required onchange="this.form.filtre.disabled = true; this.form.submit()">
            <option value="">Choisir un parrain…</option>
            @foreach ($parrains as $p)
              <option value="{{ $p->id }}" @selected($parrain?->id === $p->id)>{{ $p->nom }}</option>
            @endforeach
          </select>
          @if ($parrain)
            <div class="form-text">Zone : {{ $parrain->libelleZones() }}</div>
          @endif
        </div>

        @if ($parrain)
          <div class="col-md-6">
            <span class="form-label d-block">Source</span>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="source" id="source-eligibles" value="eligibles" @checked($f['source'] === 'eligibles')>
              <label class="form-check-label" for="source-eligibles">OEV éligibles sans appui de cette nature pour l’année</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="source" id="source-attente" value="attente" @checked($f['source'] === 'attente')>
              <label class="form-check-label" for="source-attente">Liste d’attente d’une session</label>
            </div>
          </div>
          <div class="col-sm-4 col-lg-3">
            <label class="form-label" for="annee">Année scolaire</label>
            <input class="form-control" id="annee" name="annee" value="{{ $f['annee'] }}" pattern="\d{4}-\d{4}">
          </div>
          <div class="col-sm-8 col-lg-4">
            <label class="form-label" for="nature_id">Nature d’appui</label>
            <select class="form-select" id="nature_id" name="nature_id">
              <option value="">Toutes</option>
              @foreach ($natures->sortBy(fn ($n) => in_array($n->id, $f['natures_parrain'], true) ? 0 : 1) as $nature)
                <option value="{{ $nature->id }}" @selected($f['nature']?->id === $nature->id)>{{ $nature->libelle }}@if (in_array($nature->id, $f['natures_parrain'], true)) (proposée par le parrain)@endif</option>
              @endforeach
            </select>
          </div>
          <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="session_liste">Session <span class="text-muted small">(liste d’attente)</span></label>
            <select class="form-select" id="session_liste" name="session_id">
              <option value="">—</option>
              @foreach ($sessions as $session)
                <option value="{{ $session->id }}" @selected($f['session']?->id === $session->id)>{{ $session->reference() }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-sm-6 col-lg-2">
            <label class="form-label" for="sexe">Sexe</label>
            <select class="form-select" id="sexe" name="sexe">
              <option value="">Les deux</option>
              @foreach (Oev::SEXES as $cle => $libelle)
                <option value="{{ $cle }}" @selected($f['sexe'] === $cle)>{{ $libelle }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="zone_parrain" name="zone_parrain" value="1" @checked($f['zone_parrain'])>
              <label class="form-check-label" for="zone_parrain">Limiter à la zone d’intervention du parrain</label>
            </div>
          </div>
          <div class="col-12">
            @include('partials.localite-selects', [
              'localites' => $localites,
              'requis' => false,
              'valeurs' => ['region_id' => $f['region_id'], 'province_id' => $f['province_id'], 'commune_id' => $f['commune_id']],
              'colonnes' => ['region_id' => 'col-md-4', 'province_id' => 'col-md-4', 'commune_id' => 'col-md-4'],
            ])
          </div>

          @if ($central)
            <div class="col-12">
              <div class="border rounded p-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="identite_complete" name="identite_complete" value="1" @checked(old('identite_complete'))>
                  <label class="form-check-label fw-semibold" for="identite_complete">Version avec identité complète (code, nom, prénom, date de naissance)</label>
                </div>
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" id="confirmation_identite" name="confirmation_identite" value="1">
                  <label class="form-check-label small" for="confirmation_identite">Je confirme que ces données personnelles d’enfants sont transmises dans le cadre d’une convention avec ce parrain. L’extraction sera tracée.</label>
                </div>
              </div>
            </div>
          @endif
        @endif
      </div>

      @if ($parrain)
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Actualiser l’aperçu</button>
          <button class="btn btn-outline-primary" type="submit" formaction="{{ route('parrainage.pilotage.liste-parrain') }}" name="format" value="pdf"><i class="bi bi-filetype-pdf" aria-hidden="true"></i> PDF</button>
          <button class="btn btn-primary" type="submit" formaction="{{ route('parrainage.pilotage.liste-parrain') }}" name="format" value="xlsx"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel</button>
        </div>
      @endif
    </form>

    @if ($parrain)
      <section class="panel mt-3">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-eye" aria-hidden="true"></i><span>Aperçu anonymisé</span></h2>
            <p class="text-muted mb-0">
              {{ $liste->count() }} OEV correspondent aux filtres{{ $liste->count() > 10 ? ' (10 premiers affichés)' : '' }}, par ordre de priorité.
              @if ($f['source'] === 'attente' && ! $disponible) Liste d’attente : données non disponibles (module Sélection). @endif
            </p>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr>@foreach (array_keys($colonnesApercu) as $titre)<th scope="col">{{ $titre }}</th>@endforeach</tr></thead>
            <tbody>
              @forelse ($apercu as $oev)
                <tr>@foreach ($colonnesApercu as $titre => $valeur)<td>{{ str_contains($titre, 'FCFA') ? \App\Support\Montant::nombre($valeur($oev)) : $valeur($oev) }}</td>@endforeach</tr>
              @empty
                <tr><td colspan="{{ count($colonnesApercu) }}" class="text-center text-muted py-3">Aucun OEV ne correspond à ces filtres.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </section>
    @endif
  @endif
</div>
@endsection
