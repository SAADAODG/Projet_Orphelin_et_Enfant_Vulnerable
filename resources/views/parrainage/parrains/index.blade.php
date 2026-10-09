@extends('layouts.app')
@use('App\Models\Parrain')

@section('title', 'Répertoire des parrains | ' . $siteSetting->structure_nom)

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
        <p class="text-muted mb-0">ONG, associations, entreprises, institutions et particuliers qui appuient les OEV.</p>
      </div>
    </div>
    @can('gérer parrains')
      <div class="heading-actions">
        <a class="btn btn-primary btn-sm" href="{{ route('parrainage.parrains.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouveau parrain</a>
      </div>
    @endcan
  </div>

  <div class="mt-3">@include('parrainage.parrains._onglets')</div>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-person-vcard" aria-hidden="true"></i><span>Répertoire des parrains</span></h2>
        <p class="text-muted mb-0">{{ $parrains->total() }} parrain(s).</p>
      </div>
      <form class="d-flex flex-wrap gap-2" method="GET" action="{{ route('parrainage.parrains.index') }}">
        <input class="form-control form-control-sm w-auto" type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Nom ou contact" aria-label="Rechercher un parrain">
        <select class="form-select form-select-sm w-auto" name="type" aria-label="Filtrer par type" onchange="this.form.submit()">
          <option value="">Tous les types</option>
          @foreach (Parrain::TYPES as $cle => $libelle)
            <option value="{{ $cle }}" @selected($filtres['type'] === $cle)>{{ $libelle }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm w-auto" name="region_id" aria-label="Filtrer par zone d’intervention" onchange="this.form.submit()">
          <option value="">Toutes les zones</option>
          @foreach ($regions as $id => $nom)
            <option value="{{ $id }}" @selected($filtres['region_id'] === $id)>{{ $nom }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm w-auto" name="actif" aria-label="Filtrer par statut" onchange="this.form.submit()">
          <option value="">Actifs et inactifs</option>
          <option value="1" @selected($filtres['actif'] === '1')>Actifs</option>
          <option value="0" @selected($filtres['actif'] === '0')>Inactifs</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="submit" aria-label="Rechercher"><i class="bi bi-search" aria-hidden="true"></i></button>
        @if (array_filter($filtres, fn ($v) => $v !== null && $v !== ''))
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.parrains.index') }}"><i class="bi bi-x-lg" aria-hidden="true"></i> Effacer</a>
        @endif
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Parrain</th>
            <th scope="col">Type</th>
            <th scope="col">Contact</th>
            <th scope="col">Zone d’intervention</th>
            <th scope="col" class="text-end">OEV appuyés</th>
            <th scope="col">Statut</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($parrains as $parrain)
            <tr @class(['text-muted' => ! $parrain->actif])>
              <td class="fw-semibold">{{ $parrain->nom }}</td>
              <td>{{ $parrain->libelleType() }}</td>
              <td>
                {{ $parrain->contact_nom ?? '—' }}
                @if ($parrain->telephone)<span class="d-block small text-muted">{{ $parrain->telephone }}</span>@endif
              </td>
              <td class="small">{{ $parrain->libelleZones() }}</td>
              <td class="text-end">{{ $parrain->oev_appuyes_count }}</td>
              <td>
                <span class="badge rounded-pill text-bg-{{ $parrain->actif ? 'success' : 'secondary' }}">{{ $parrain->actif ? 'Actif' : 'Inactif' }}</span>
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a class="btn btn-light btn-sm" href="{{ route('parrainage.parrains.show', $parrain) }}" aria-label="Fiche de {{ $parrain->nom }}"><i class="bi bi-eye" aria-hidden="true"></i></a>
                  @can('gérer parrains')
                    <a class="btn btn-light btn-sm" href="{{ route('parrainage.parrains.edit', $parrain) }}" aria-label="Modifier {{ $parrain->nom }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                  @endcan
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Aucun parrain trouvé.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($parrains->hasPages())
      <div class="d-flex justify-content-center mt-3">
        {{ $parrains->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>
@endsection
