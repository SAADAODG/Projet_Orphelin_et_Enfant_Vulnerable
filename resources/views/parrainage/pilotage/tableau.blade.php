@extends('layouts.app')
@use('App\Models\Oev')
@use('App\Models\SessionParrainage')
@use('App\Models\User')
@use('App\Support\Montant')

@section('title', 'Pilotage du parrainage | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/barres.css') }}">
<style>
  .pil-filtres { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
  .pil-filtres .form-select { width: auto; min-width: 9rem; }
  .pil-nd { color: var(--admin-muted); font-size: .95rem; font-weight: 600; }
  @media (max-width: 575.98px) { .pil-filtres .form-select { width: 100%; } }
</style>
@endpush

@php
  $b = $indicateurs['budget'];
  $c = $indicateurs['couverture'];
  $p = $indicateurs['profil'];
  $pa = $indicateurs['partenaires'];
  $nd = 'Données non disponibles';
  $cartes = [
      ['libelle' => 'Enveloppe', 'valeur' => Montant::fcfa($b['enveloppe']), 'aide' => $b['sessions'] . ' session(s) en ' . $f['annee'], 'icone' => 'bi-wallet2', 'couleur' => 'primary'],
      ['libelle' => 'Montant engagé', 'valeur' => $b['engage'] === null ? null : Montant::fcfa($b['engage']), 'aide' => $b['taux'] === null ? 'Liste définitive' : "{$b['taux']} % de l’enveloppe", 'icone' => 'bi-cash-stack', 'couleur' => 'warning'],
      ['libelle' => 'OEV éligibles', 'valeur' => Montant::nombre($c['eligibles']), 'aide' => 'Intégrés et actifs', 'icone' => 'bi-people', 'couleur' => 'success'],
      ['libelle' => 'OEV appuyés', 'valeur' => Montant::nombre($c['appuyes']), 'aide' => $c['taux'] === null ? 'État et partenaires' : "Couverture : {$c['taux']} %", 'icone' => 'bi-heart', 'couleur' => 'danger'],
  ];
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading flex-wrap">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage</p>
        <h1 class="h3 mb-1">Pilotage</h1>
        <p class="text-muted mb-0"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $f['perimetre']->libelle() }} · année {{ $f['annee'] }}{{ $f['session'] ? ' · session ' . $f['session']->reference() : '' }}</p>
      </div>
    </div>
  </div>

  <div class="mt-3">@include('parrainage.pilotage._onglets')</div>

  <form class="pil-filtres panel mt-3" method="GET" action="{{ route('parrainage.pilotage.index') }}" aria-label="Filtrer le tableau de bord">
    <label class="visually-hidden" for="filtre-annee">Année</label>
    <input class="form-control form-control-sm w-auto" id="filtre-annee" name="annee" value="{{ $f['annee'] }}" pattern="\d{4}-\d{4}" size="9" title="Année scolaire, ex : 2026-2027" onchange="this.form.session_id.value = ''; this.form.submit()">
    <label class="visually-hidden" for="filtre-session">Session</label>
    <select class="form-select form-select-sm" id="filtre-session" name="session_id" onchange="this.form.submit()">
      <option value="">Toutes les sessions de l’année</option>
      @foreach ($f['sessions'] as $session)
        <option value="{{ $session->id }}" @selected($f['session']?->id === $session->id)>Session n°{{ $session->numero }} — {{ $session->description }}</option>
      @endforeach
    </select>
    <label class="visually-hidden" for="filtre-type">Type d’appui</label>
    <select class="form-select form-select-sm" id="filtre-type" name="type_appui" onchange="this.form.submit()">
      <option value="">Tous les types d’appui</option>
      @foreach (SessionParrainage::TYPES_APPUI as $cle => $libelle)
        <option value="{{ $cle }}" @selected($f['type_appui'] === $cle)>{{ $libelle }}</option>
      @endforeach
    </select>
    <label class="visually-hidden" for="filtre-sexe">Sexe</label>
    <select class="form-select form-select-sm" id="filtre-sexe" name="sexe" onchange="this.form.submit()">
      <option value="">Les deux sexes</option>
      @foreach (Oev::SEXES as $cle => $libelle)
        <option value="{{ $cle }}" @selected($f['sexe'] === $cle)>{{ $libelle }}</option>
      @endforeach
    </select>
    @if ($f['perimetre']->niveau === User::NIVEAU_CENTRAL)
      <label class="visually-hidden" for="filtre-region">Région</label>
      <select class="form-select form-select-sm" id="filtre-region" name="region_id" onchange="this.form.province_id && (this.form.province_id.value = ''); this.form.submit()">
        <option value="">Toutes les régions</option>
        @foreach ($regions as $region)
          <option value="{{ $region->id }}" @selected($f['perimetre']->region?->id === $region->id)>{{ $region->nom }}</option>
        @endforeach
      </select>
      @if ($f['perimetre']->region)
        <label class="visually-hidden" for="filtre-province">Province</label>
        <select class="form-select form-select-sm" id="filtre-province" name="province_id" onchange="this.form.submit()">
          <option value="">Toutes les provinces</option>
          @foreach ($regions->firstWhere('id', $f['perimetre']->region->id)?->provinces ?? [] as $province)
            <option value="{{ $province->id }}" @selected($f['perimetre']->province?->id === $province->id)>{{ $province->nom }}</option>
          @endforeach
        </select>
      @endif
    @endif
    <noscript><button class="btn btn-outline-secondary btn-sm" type="submit">Filtrer</button></noscript>
  </form>

  @unless ($disponible)
    <div class="alert alert-info small mt-3 mb-0">
      <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
      Les chiffres de l’État (liste définitive, liste d’attente, montant engagé, résultats scolaires) s’afficheront quand les modules Sélection et Suivi scolaire seront branchés.
      Les appuis des partenaires sont déjà comptés.
    </div>
  @endunless

  <section class="row g-3 mt-1" aria-label="Indicateurs clés">
    @foreach ($cartes as $carte)
      <div class="col-12 col-sm-6 col-xl-3">
        <article class="metric-card metric-{{ $carte['couleur'] }}">
          <div class="metric-top">
            <span class="metric-label">{{ $carte['libelle'] }}</span>
            <span class="metric-icon"><i class="bi {{ $carte['icone'] }}" aria-hidden="true"></i></span>
          </div>
          <div class="metric-value">@if ($carte['valeur'] === null)<span class="pil-nd">{{ $nd }}</span>@else{{ $carte['valeur'] }}@endif</div>
          <div class="metric-meta"><span>{{ $carte['aide'] }}</span></div>
        </article>
      </div>
    @endforeach
  </section>

  <div class="row g-3 mt-1">
    {{-- Budget --}}
    <div class="col-12 col-xl-6">
      <section class="oev-carte h-100">
        <h2 class="oev-carte-titre"><i class="bi bi-wallet2" aria-hidden="true"></i> Budget</h2>
        <div class="oev-infos">
          <div class="oev-info"><span class="oev-info-label">Enveloppe</span><span class="oev-info-valeur">{{ Montant::fcfa($b['enveloppe']) }}</span></div>
          <div class="oev-info"><span class="oev-info-label">Montant engagé (liste définitive)</span><span class="oev-info-valeur">{{ $b['engage'] === null ? $nd : Montant::fcfa($b['engage']) }}</span></div>
          <div class="oev-info"><span class="oev-info-label">Reste</span><span class="oev-info-valeur">{{ $b['reste'] === null ? $nd : Montant::fcfa($b['reste']) }}</span></div>
          <div class="oev-info"><span class="oev-info-label">Taux de consommation</span><span class="oev-info-valeur">{{ $b['taux'] === null ? $nd : $b['taux'] . ' %' }}</span></div>
        </div>
        @if ($b['quotas'])
          <h3 class="h6 mt-3">Par région (quotas)</h3>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th scope="col">Région</th><th scope="col" class="text-end">Quota</th><th scope="col" class="text-end">Engagé</th><th scope="col" class="text-end">Reste</th></tr></thead>
              <tbody>
                @foreach ($b['quotas'] as $ligne)
                  <tr>
                    <td>{{ $ligne['region'] }}</td>
                    <td class="text-end text-nowrap">{{ Montant::fcfa($ligne['quota']) }}</td>
                    <td class="text-end text-nowrap">{{ $ligne['engage'] === null ? '—' : Montant::fcfa($ligne['engage']) }}</td>
                    <td class="text-end text-nowrap @if (($ligne['reste'] ?? 0) < 0) text-danger @endif">{{ $ligne['reste'] === null ? '—' : Montant::fcfa($ligne['reste']) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @elseif (! $f['session'])
          <p class="text-muted small mt-3 mb-0">Choisissez une session pour voir ses quotas par région.</p>
        @endif
      </section>
    </div>

    {{-- Couverture --}}
    <div class="col-12 col-xl-6">
      <section class="oev-carte h-100">
        <h2 class="oev-carte-titre"><i class="bi bi-bullseye" aria-hidden="true"></i> Couverture</h2>
        @include('parrainage.pilotage._barres', ['couleur' => 'primary', 'lignes' => [
            ['libelle' => 'OEV éligibles', 'total' => $c['eligibles']],
            ['libelle' => 'Bénéficiaires de l’État', 'total' => $c['etat'] ?? 0],
            ['libelle' => 'Appuyés par un partenaire', 'total' => $c['partenaires']],
            ['libelle' => 'Liste d’attente', 'total' => $c['attente'] ?? 0],
        ], 'vide' => 'Aucun OEV éligible dans cette zone.'])
        <p class="small text-muted mt-3 mb-0">
          Taux de couverture : <strong>{{ $c['taux'] === null ? '—' : $c['taux'] . ' %' }}</strong>
          ({{ $c['appuyes'] }} OEV appuyé(s) par l’État ou un partenaire sur {{ $c['eligibles'] }}).
          @if ($c['etat'] === null) Bénéficiaires de l’État et liste d’attente : {{ mb_strtolower($nd) }}. @endif
        </p>
      </section>
    </div>

    {{-- Profil --}}
    <div class="col-12">
      <section class="oev-carte">
        <h2 class="oev-carte-titre"><i class="bi bi-person-badge" aria-hidden="true"></i> Profil des OEV appuyés <span class="text-muted small fw-normal">({{ $p['total'] }})</span></h2>
        <div class="row g-4">
          <div class="col-12 col-md-6 col-xl-4">
            <h3 class="h6">Sexe</h3>
            @include('parrainage.pilotage._barres', ['couleur' => 'info', 'lignes' => $p['sexe'], 'vide' => 'Aucun OEV appuyé.'])
            <h3 class="h6 mt-4">Handicap</h3>
            @include('parrainage.pilotage._barres', ['couleur' => 'secondary', 'lignes' => $p['handicap'], 'vide' => 'Aucun OEV appuyé.'])
          </div>
          <div class="col-12 col-md-6 col-xl-4">
            <h3 class="h6">Âge</h3>
            @include('parrainage.pilotage._barres', ['couleur' => 'primary', 'lignes' => $p['age'], 'vide' => 'Aucun OEV appuyé.'])
            <h3 class="h6 mt-4">Cycle ou filière</h3>
            @include('parrainage.pilotage._barres', ['couleur' => 'success', 'lignes' => $p['cycle'], 'vide' => 'Aucun OEV appuyé.'])
          </div>
          <div class="col-12 col-xl-4">
            <h3 class="h6">Types de vulnérabilité</h3>
            @include('parrainage.pilotage._barres', ['couleur' => 'warning', 'lignes' => $p['vulnerabilites'], 'vide' => 'Aucune vulnérabilité renseignée.'])
          </div>
        </div>
      </section>
    </div>

    {{-- Partenaires --}}
    <div class="col-12 col-xl-7">
      <section class="oev-carte h-100">
        <h2 class="oev-carte-titre"><i class="bi bi-people" aria-hidden="true"></i> Partenaires</h2>
        <p class="mb-3">
          <strong>{{ $pa['actifs'] }}</strong> parrain(s) actif(s) dans la zone ·
          <strong>{{ $c['partenaires'] }}</strong> OEV appuyé(s) · <strong>{{ Montant::fcfa($pa['montant']) }}</strong>
        </p>
        @if ($pa['parParrain']->isEmpty())
          <p class="text-muted small mb-0">Aucun appui de partenaire pour {{ $f['annee'] }}.</p>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th scope="col">Parrain</th><th scope="col" class="text-end">OEV appuyés</th><th scope="col" class="text-end">Montant</th></tr></thead>
              <tbody>
                @foreach ($pa['parParrain'] as $ligne)
                  <tr><td>{{ $ligne['libelle'] }}</td><td class="text-end">{{ $ligne['oev'] }}</td><td class="text-end text-nowrap">{{ Montant::fcfa($ligne['montant']) }}</td></tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </section>
    </div>
    <div class="col-12 col-xl-5">
      <section class="oev-carte h-100">
        <h2 class="oev-carte-titre"><i class="bi bi-gift" aria-hidden="true"></i> Par nature d’appui</h2>
        @include('parrainage.pilotage._barres', ['couleur' => 'success', 'lignes' => $pa['parNature']->map(fn ($l) => ['libelle' => $l['libelle'], 'total' => $l['oev']]), 'vide' => 'Aucun appui de partenaire.'])
        @if ($pa['parNature']->isNotEmpty())
          <ul class="list-unstyled small text-muted mt-3 mb-0">
            @foreach ($pa['parNature'] as $ligne)
              <li>{{ $ligne['libelle'] }} : {{ Montant::fcfa($ligne['montant']) }}</li>
            @endforeach
          </ul>
        @endif
      </section>
    </div>

    {{-- Résultats --}}
    <div class="col-12">
      <section class="oev-carte">
        <h2 class="oev-carte-titre"><i class="bi bi-mortarboard" aria-hidden="true"></i> Résultats des OEV appuyés</h2>
        @if ($indicateurs['resultats'] === null)
          <p class="text-muted small mb-0">{{ $nd }} : les résultats viendront du module Suivi scolaire.</p>
        @else
          @include('parrainage.pilotage._barres', ['couleur' => 'primary', 'lignes' => $indicateurs['resultats'], 'vide' => 'Aucun résultat enregistré pour cette année.'])
          <p class="small text-muted mt-3 mb-0">
            @foreach ($indicateurs['resultats'] as $ligne)
              {{ $ligne['libelle'] }} : {{ $ligne['taux'] === null ? '—' : $ligne['taux'] . ' %' }}@unless ($loop->last) · @endunless
            @endforeach
          </p>
        @endif
      </section>
    </div>
  </div>
</div>
@endsection
