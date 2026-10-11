@extends('layouts.app')
@use('App\Support\Montant')

@section('title', 'Appuis des parrains | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage</p>
        <h1 class="h3 mb-1">Parrains</h1>
        <p class="text-muted mb-0">Appuis apportés par les parrains aux OEV, année par année. Un OEV ne reçoit qu’un appui de même nature par an (RG-01).</p>
      </div>
    </div>
    @can('enregistrer appuis')
      <div class="heading-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.appuis.import') }}"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i> Importer (Excel)</a>
        <a class="btn btn-primary btn-sm" href="{{ route('parrainage.appuis.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouvel appui</a>
      </div>
    @endcan
  </div>

  <div class="mt-3">@include('parrainage.parrains._onglets')</div>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1">Appuis enregistrés <span class="badge rounded-pill text-bg-light border ms-1">{{ $appuis->total() }}</span></h2>
        <p class="text-muted mb-0">
          @if (array_filter($filtres))
            Résultats filtrés — <a href="{{ route('parrainage.appuis.index') }}" class="text-decoration-none">retirer les filtres</a>
          @else
            Appuis des parrains aux OEV de votre zone.
          @endif
        </p>
      </div>
    </div>
      <form class="oev-toolbar" method="GET" action="{{ route('parrainage.appuis.index') }}" role="search">
        <div class="oev-recherche">
          <i class="bi bi-search" aria-hidden="true"></i>
          <input class="form-control" type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Code, nom ou prénom de l’OEV" aria-label="Rechercher un OEV">
        </div>
        <select class="form-select" name="annee" aria-label="Filtrer par année" onchange="this.form.submit()">
          <option value="">Toutes les années</option>
          @foreach ($annees as $annee)
            <option value="{{ $annee }}" @selected($filtres['annee'] === $annee)>{{ $annee }}</option>
          @endforeach
        </select>
        <select class="form-select" name="nature" aria-label="Filtrer par nature" onchange="this.form.submit()">
          <option value="">Toutes les natures</option>
          @foreach ($natures as $nature)
            <option value="{{ $nature->id }}" @selected($filtres['nature'] === $nature->id)>{{ $nature->libelle }}</option>
          @endforeach
        </select>
        <select class="form-select" name="parrain" aria-label="Filtrer par parrain" onchange="this.form.submit()">
          <option value="">Tous les parrains</option>
          @foreach ($parrains as $id => $nom)
            <option value="{{ $id }}" @selected($filtres['parrain'] === $id)>{{ $nom }}</option>
          @endforeach
        </select>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Rechercher</button>
      </form>
    <div class="table-responsive">
      <table class="table align-middle mb-0 oev-liste">
        <thead>
          <tr>
            <th scope="col">OEV</th>
            <th scope="col">Parrain</th>
            <th scope="col">Année</th>
            <th scope="col">Nature</th>
            <th scope="col" class="text-end">Montant</th>
            <th scope="col">Période</th>
            @can('enregistrer appuis')<th scope="col" class="text-end">Action</th>@endcan
          </tr>
        </thead>
        <tbody>
          @forelse ($appuis as $appui)
            <tr>
              <td>@include('parrainage._oev', ['oev' => $appui->oev, 'detail' => $appui->oev->commune ? '· ' . $appui->oev->commune->nom : ''])</td>
              <td>{{ $appui->parrain->nom }}</td>
              <td>{{ $appui->annee }}</td>
              <td>
                {{ $appui->libelleNature() }}
                @if ($appui->motif_doublon)
                  <span class="badge rounded-pill text-bg-warning" title="{{ $appui->motif_doublon }}">2e appui de même nature</span>
                @endif
              </td>
              <td class="text-end text-nowrap">{{ $appui->montant !== null ? Montant::fcfa($appui->montant) : 'En nature' }}</td>
              <td class="small text-nowrap">
                @if ($appui->date_debut || $appui->date_fin)
                  {{ $appui->date_debut?->format('d/m/Y') ?? '…' }} → {{ $appui->date_fin?->format('d/m/Y') ?? '…' }}
                @else
                  —
                @endif
              </td>
              @can('enregistrer appuis')
                <td class="text-end">
                  <a class="btn btn-light btn-sm" href="{{ route('parrainage.appuis.edit', $appui) }}" aria-label="Modifier l’appui"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                </td>
              @endcan
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Aucun appui enregistré.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($appuis->total())
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <small class="text-muted">Affichage de {{ $appuis->firstItem() }} à {{ $appuis->lastItem() }} sur {{ $appuis->total() }} appui(s)</small>
        {{ $appuis->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>
@endsection
