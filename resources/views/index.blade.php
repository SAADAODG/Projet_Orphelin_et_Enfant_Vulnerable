@extends('layouts.app')

@section('title', 'Ministère de la Famille et de la Solidarité | Tableau de bord')

@use('App\Models\Oev')
@use('App\Models\Plainte')
@use('App\Models\Signalement')
@use('App\Models\User')

@php
  $niveaux = [
    User::NIVEAU_CENTRAL => 'Niveau central',
    User::NIVEAU_REGION => 'Direction régionale',
    User::NIVEAU_PROVINCE => 'Direction provinciale',
  ];
  $niveau = $perimetre->niveau;
  // Panneaux Signalements / Plaintes : seulement si l'utilisateur a accès au module
  $avecSignalements = $signalements !== null;
  $avecPlaintes = $plaintes !== null;
  $maxCircuit = max(1, $circuit->max('total'));
  $maxEvolution = max(1, $evolution->max('signalements'), $evolution->max('integres'));
  $maxRepartition = max(1, $repartition->max(fn ($l) => max($l['enCours'], $l['integres'])));
  $maxIntegres = max(1, $oevIntegres['total']);
  $titreATraiter = match ($niveau) {
    User::NIVEAU_PROVINCE => ['Dossiers à reprendre', 'Non conformes en premier, puis dossiers en constitution.'],
    User::NIVEAU_REGION => ['Dossiers à vérifier', 'Compléments demandés en premier, puis dossiers soumis.'],
    default => ['Dossiers à intégrer', 'Validés par les DR, les plus anciens d’abord.'],
  };
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/barres.css') }}">
<style>
  .db-colonnes { display: grid; grid-template-columns: repeat({{ $evolution->count() }}, minmax(2.5rem, 1fr)); gap: 1rem; height: 15rem; align-items: end; padding-top: 1.25rem; }
  .db-mois { height: 100%; display: grid; grid-template-rows: 1fr auto; gap: .5rem; text-align: center; color: var(--admin-muted); font-size: .82rem; font-weight: 700; }
  .db-mois-barres { display: flex; align-items: flex-end; justify-content: center; gap: 4px; height: 100%; }
  .db-mois-barres > span { position: relative; width: min(1.4rem, 40%); height: var(--v); min-height: 2px; border-radius: 4px 4px 0 0; background: var(--db-couleur); }
  .db-mois-barres > span::after { content: attr(data-valeur); position: absolute; bottom: calc(100% + 2px); left: 50%; transform: translateX(-50%); font-size: .72rem; color: var(--admin-text); }
  .db-legende { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .85rem; color: var(--admin-muted); }
  .db-legende i { display: inline-block; width: .7rem; height: .7rem; border-radius: 2px; margin-inline-end: .35rem; background: var(--db-couleur); vertical-align: -1px; }

  .db-repartition td { vertical-align: middle; }
  .db-repartition .db-piste { min-width: 4rem; }
  .db-filtre { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
  .db-filtre .form-select { width: auto; min-width: 11rem; }
  .db-a-traiter { display: grid; gap: .75rem; }
  .db-a-traiter a { display: flex; justify-content: space-between; gap: .75rem; align-items: center; padding: .7rem .85rem; border: 1px solid var(--admin-border); border-radius: 8px; color: inherit; }
  .db-a-traiter a:hover { border-color: var(--admin-primary); }
  .metric-card a.stretched-link:focus-visible { outline: none; }
  .metric-card:has(a.stretched-link:focus-visible) { box-shadow: var(--admin-ring); }

  @media (max-width: 575.98px) {
    .db-filtre .form-select { width: 100%; }
  }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading flex-wrap">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">{{ $niveaux[$niveau] }}</p>
        <h1 class="h3 mb-1">Tableau de bord</h1>
        <p class="text-muted mb-0"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $perimetre->libelle() }} · au {{ now()->locale('fr')->translatedFormat('j F Y') }}</p>
      </div>
    </div>

    @if ($niveau === User::NIVEAU_CENTRAL)
      <form class="db-filtre" method="GET" action="{{ route('dashboard') }}" aria-label="Filtrer le tableau de bord par localité">
        <label class="visually-hidden" for="filtre-region">Région</label>
        <select class="form-select form-select-sm" id="filtre-region" name="region_id" onchange="this.form.province_id && (this.form.province_id.value = ''); this.form.submit()">
          <option value="">Toutes les régions</option>
          @foreach ($regions as $region)
            <option value="{{ $region->id }}" @selected($perimetre->region?->id === $region->id)>{{ $region->nom }}</option>
          @endforeach
        </select>
        @if ($perimetre->region)
          <label class="visually-hidden" for="filtre-province">Province</label>
          <select class="form-select form-select-sm" id="filtre-province" name="province_id" onchange="this.form.submit()">
            <option value="">Toutes les provinces</option>
            @foreach ($regions->firstWhere('id', $perimetre->region->id)->provinces as $province)
              <option value="{{ $province->id }}" @selected($perimetre->province?->id === $province->id)>{{ $province->nom }}</option>
            @endforeach
          </select>
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboard') }}">Tout le pays</a>
        @endif
        <noscript><button class="btn btn-primary btn-sm" type="submit">Filtrer</button></noscript>
      </form>
    @endif
  </div>

  @unless ($perimetre->defini)
    <div class="alert alert-warning d-flex gap-2" role="alert">
      <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
      <div>
        Votre compte n’est rattaché à aucune {{ $niveau === User::NIVEAU_REGION ? 'région' : 'province' }} : aucune statistique ne peut être affichée.
        Demandez à un administrateur de compléter votre compte dans « Gestion des utilisateurs ».
      </div>
    </div>
  @endunless

  {{-- Ce qui attend une action à ce niveau --}}
  <section class="row g-3" aria-label="Indicateurs prioritaires">
    @foreach ($indicateurs as $carte)
      @php
        $lien = $carte['route'] && (! $carte['permission'] || $utilisateur->can($carte['permission']))
          ? route($carte['route'][0], $carte['route'][1] + array_filter(['region_id' => $niveau === User::NIVEAU_CENTRAL ? $perimetre->region?->id : null]))
          : null;
      @endphp
      <div class="col-12 col-sm-6 col-xl-3">
        <article class="metric-card metric-{{ $carte['couleur'] }} position-relative">
          <div class="metric-top">
            <span class="metric-label">{{ $carte['libelle'] }}</span>
            <span class="metric-icon"><i class="bi {{ $carte['icone'] }}" aria-hidden="true"></i></span>
          </div>
          <div class="metric-value">{{ number_format($carte['valeur'], 0, ',', ' ') }}</div>
          <div class="metric-meta">
            <span>{{ $carte['aide'] }}</span>
            @if ($lien)
              <a class="stretched-link ms-auto" href="{{ $lien }}" aria-label="Voir : {{ $carte['libelle'] }}"><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            @endif
          </div>
        </article>
      </div>
    @endforeach
  </section>

  <section class="row g-3 mt-1">
    {{-- Circuit DP → DR → central --}}
    <div class="col-12 col-xl-7">
      <div class="panel h-100">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-diagram-3" aria-hidden="true"></i><span>Circuit des dossiers enfants</span></h2>
            <p class="text-muted mb-0">{{ number_format($circuit->sum('total'), 0, ',', ' ') }} dossier(s), de la constitution par le DP à l’intégration comme OEV.</p>
          </div>
        </div>
        @foreach ($circuit as $etape)
          <div class="db-barre db-{{ $etape['couleur'] }}">
            <span class="db-barre-libelle" title="{{ $etape['libelle'] }}">{{ $etape['libelle'] }}</span>
            <span class="db-piste" role="img" aria-label="{{ $etape['libelle'] }} : {{ $etape['total'] }}"><span style="--v: {{ round($etape['total'] / $maxCircuit * 100, 1) }}%" @if (! $etape['total']) data-zero @endif></span></span>
            <span class="db-barre-valeur">{{ $etape['total'] }}</span>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Dossiers en attente d'une action de l'utilisateur --}}
    <div class="col-12 col-xl-5">
      <div class="panel h-100">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-list-check" aria-hidden="true"></i><span>{{ $titreATraiter[0] }}</span></h2>
            <p class="text-muted mb-0">{{ $titreATraiter[1] }}</p>
          </div>
        </div>
        @if ($aTraiter->isEmpty())
          <p class="text-muted mb-0"><i class="bi bi-check2-circle text-success" aria-hidden="true"></i> Aucun dossier en attente.</p>
        @else
          <div class="db-a-traiter">
            @foreach ($aTraiter as $oev)
              <a href="{{ route('oevs.show', $oev) }}">
                <span class="min-w-0">
                  <span class="d-block fw-semibold text-truncate">{{ $oev->nomComplet() }}</span>
                  <small class="text-muted">{{ $oev->numero_dossier }} · {{ $niveau === User::NIVEAU_PROVINCE ? $oev->commune?->nom : $oev->province?->nom }}
                    @if ($oev->statut_dossier === Oev::ETAT_BROUILLON) · {{ $oev->documents_count }}/{{ count(Oev::DOCUMENTS) }} pièces @endif
                  </small>
                </span>
                <span class="badge text-bg-{{ $oev->couleurEtat() }}">{{ $oev->libelleEtat() }}</span>
              </a>
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </section>

  <section class="row g-3 mt-1">
    {{-- Évolution mensuelle --}}
    <div class="col-12 col-xl-8">
      <div class="panel h-100">
        <div class="panel-header flex-wrap">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i><span>Évolution sur {{ $evolution->count() }} mois</span></h2>
            <p class="text-muted mb-0">{{ $avecSignalements ? 'Signalements reçus et enfants intégrés comme OEV' : 'Enfants intégrés comme OEV' }}, par mois.</p>
          </div>
          <div class="db-legende" aria-hidden="true">
            @if ($avecSignalements)<span class="db-warning"><i></i>Signalements</span>@endif
            <span class="db-success"><i></i>OEV intégrés</span>
          </div>
        </div>
        <div class="db-colonnes" role="img" aria-label="{{ $evolution->map(fn ($m) => $m['moisComplet'] . ' : ' . ($avecSignalements ? "{$m['signalements']} signalement(s), " : '') . "{$m['integres']} OEV intégré(s)")->implode(' ; ') }}">
          @foreach ($evolution as $mois)
            <div class="db-mois" title="{{ $mois['moisComplet'] }}">
              <div class="db-mois-barres">
                @if ($avecSignalements)
                  <span class="db-warning" style="--v: {{ round($mois['signalements'] / $maxEvolution * 85, 1) }}%" data-valeur="{{ $mois['signalements'] }}"></span>
                @endif
                <span class="db-success" style="--v: {{ round($mois['integres'] / $maxEvolution * 85, 1) }}%" data-valeur="{{ $mois['integres'] }}"></span>
              </div>
              <small>{{ $mois['mois'] }}</small>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Profil des OEV intégrés --}}
    <div class="col-12 col-xl-4">
      <div class="panel h-100">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-people" aria-hidden="true"></i><span>OEV intégrés</span></h2>
            <p class="text-muted mb-0">{{ number_format($oevIntegres['total'], 0, ',', ' ') }} enfant(s), dont {{ $oevIntegres['handicap'] }} en situation de handicap.</p>
          </div>
        </div>
        <h3 class="h6 text-muted">Par sexe</h3>
        @foreach ($oevIntegres['parSexe'] as $ligne)
          <div class="db-barre db-{{ $loop->first ? 'primary' : 'info' }}">
            <span class="db-barre-libelle">{{ $ligne['libelle'] }}</span>
            <span class="db-piste" role="img" aria-label="{{ $ligne['libelle'] }} : {{ $ligne['total'] }}"><span style="--v: {{ round($ligne['total'] / $maxIntegres * 100, 1) }}%" @if (! $ligne['total']) data-zero @endif></span></span>
            <span class="db-barre-valeur">{{ $ligne['total'] }}</span>
          </div>
        @endforeach
        <h3 class="h6 text-muted mt-4">Par statut</h3>
        @foreach ($oevIntegres['parStatut'] as $ligne)
          <div class="db-barre db-success">
            <span class="db-barre-libelle" title="{{ $ligne['libelle'] }}">{{ $ligne['libelle'] }}</span>
            <span class="db-piste" role="img" aria-label="{{ $ligne['libelle'] }} : {{ $ligne['total'] }}"><span style="--v: {{ round($ligne['total'] / $maxIntegres * 100, 1) }}%" @if (! $ligne['total']) data-zero @endif></span></span>
            <span class="db-barre-valeur">{{ $ligne['total'] }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section class="row g-3 mt-1">
    {{-- Répartition géographique --}}
    <div class="col-12 {{ $avecSignalements || $avecPlaintes ? 'col-xl-8' : '' }}">
      <div class="panel h-100">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-map" aria-hidden="true"></i><span>Répartition par {{ mb_strtolower($perimetre->libelleDetail()) }}</span></h2>
            <p class="text-muted mb-0">{{ $perimetre->libelle() }} — les localités les plus actives en premier.</p>
          </div>
        </div>
        @if ($repartition->isEmpty())
          <p class="text-muted mb-0">Aucune localité à afficher.</p>
        @else
          <div class="table-responsive" style="max-height: 26rem;">
            <table class="table table-sm align-middle mb-0 db-repartition">
              <thead class="sticky-top">
                <tr>
                  <th scope="col">{{ $perimetre->libelleDetail() }}</th>
                  <th scope="col" class="text-end">Dossiers en cours</th>
                  <th scope="col" class="text-end">OEV intégrés</th>
                  @if ($avecSignalements)<th scope="col" class="text-end">Signalements</th>@endif
                  <th scope="col" class="d-none d-md-table-cell" style="width: 30%;"><span class="visually-hidden">Graphique des OEV intégrés</span></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($repartition as $ligne)
                  <tr @class(['text-muted' => ! ($ligne['enCours'] + $ligne['integres'] + $ligne['signalements'])])>
                    <th scope="row" class="fw-semibold">{{ $ligne['nom'] }}</th>
                    <td class="text-end">{{ $ligne['enCours'] }}</td>
                    <td class="text-end fw-bold">{{ $ligne['integres'] }}</td>
                    @if ($avecSignalements)<td class="text-end">{{ $ligne['signalements'] }}</td>@endif
                    <td class="d-none d-md-table-cell">
                      <span class="db-piste db-success d-block" aria-hidden="true"><span style="--v: {{ round($ligne['integres'] / $maxRepartition * 100, 1) }}%" @if (! $ligne['integres']) data-zero @endif></span></span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    {{-- Signalements (DP, DR) et plaintes (niveau central) --}}
    @if ($avecSignalements || $avecPlaintes)
    <div class="col-12 col-xl-4">
      <div class="panel h-100">
        @if ($avecSignalements)
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-megaphone" aria-hidden="true"></i><span>Signalements</span></h2>
            <p class="text-muted mb-0">{{ $signalements->sum() }} reçu(s) @if ($signalementsNonLus) · <strong class="text-danger">{{ $signalementsNonLus }} non lu(s)</strong>@endif</p>
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.signalements.index') }}">Voir</a>
        </div>
        @foreach (Signalement::STATUTS as $statut => $libelle)
          <div class="db-barre db-{{ Signalement::STATUTS_COULEURS[$statut] }}">
            <span class="db-barre-libelle">{{ $libelle }}</span>
            <span class="db-piste" role="img" aria-label="{{ $libelle }} : {{ $signalements[$statut] ?? 0 }}"><span style="--v: {{ round(($signalements[$statut] ?? 0) / max(1, $signalements->max()) * 100, 1) }}%" @if (! ($signalements[$statut] ?? 0)) data-zero @endif></span></span>
            <span class="db-barre-valeur">{{ $signalements[$statut] ?? 0 }}</span>
          </div>
        @endforeach
        @endif

        @if ($avecPlaintes)
        <div @class(['panel-header', 'mt-4 mb-3' => $avecSignalements])>
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-chat-left-text" aria-hidden="true"></i><span>Plaintes et avis</span></h2>
            <p class="text-muted mb-0">{{ $plaintes->sum() }} reçu(s)</p>
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.plaintes.index') }}">Voir</a>
        </div>
        @foreach (Plainte::STATUTS as $statut => $libelle)
          <div class="db-barre db-{{ [Plainte::NOUVELLE => 'danger', Plainte::EN_COURS => 'warning', Plainte::TRAITEE => 'success'][$statut] }}">
            <span class="db-barre-libelle">{{ $libelle }}</span>
            <span class="db-piste" role="img" aria-label="{{ $libelle }} : {{ $plaintes[$statut] ?? 0 }}"><span style="--v: {{ round(($plaintes[$statut] ?? 0) / max(1, $plaintes->max()) * 100, 1) }}%" @if (! ($plaintes[$statut] ?? 0)) data-zero @endif></span></span>
            <span class="db-barre-valeur">{{ $plaintes[$statut] ?? 0 }}</span>
          </div>
        @endforeach
        @endif
      </div>
    </div>
    @endif
  </section>
</div>
@endsection
