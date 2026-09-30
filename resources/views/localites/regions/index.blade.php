@extends('layouts.app')

@section('title', 'Régions | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-map" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Localité</p>
        <h1 class="h3 mb-1">Régions</h1>
        <p class="text-muted mb-0">Gérez les régions du Burkina Faso rattachées à la plateforme.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.provinces.index') }}"><i class="bi bi-signpost-split" aria-hidden="true"></i> Provinces</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.communes.index') }}"><i class="bi bi-pin-map" aria-hidden="true"></i> Communes</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.villages.index') }}"><i class="bi bi-house-door" aria-hidden="true"></i> Villages</a>
      <a class="btn btn-primary btn-sm" href="{{ route('localites.regions.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une région</a>
    </div>
  </div>

  @include('partials.flash')

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-map" aria-hidden="true"></i><span>Liste des régions</span></h2>
        <p class="text-muted mb-0">{{ $regions->total() }} région(s) enregistrée(s).</p>
      </div>
      <form class="d-flex flex-wrap gap-2" method="GET" action="{{ route('localites.regions.index') }}">
        <input class="form-control form-control-sm" type="search" name="q" value="{{ $search }}" placeholder="Rechercher une région" aria-label="Rechercher une région">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search" aria-hidden="true"></i></button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Région</th>
            <th scope="col">Ancienne appellation</th>
            <th scope="col">Provinces</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($regions as $region)
            <tr>
              <td class="fw-semibold">{{ $region->nom }}</td>
              <td>{{ $region->ancien_nom ?? '—' }}</td>
              <td><span class="badge text-bg-light">{{ $region->provinces_count }}</span></td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a class="btn btn-light btn-sm" href="{{ route('localites.regions.edit', $region) }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                  <form action="{{ route('localites.regions.destroy', $region) }}" method="POST" onsubmit="return confirm('Supprimer la région « {{ $region->nom }} » ?');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash" aria-hidden="true"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">Aucune région trouvée.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($regions->hasPages())
      <div class="d-flex justify-content-center mt-3">
        {{ $regions->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>
@endsection
