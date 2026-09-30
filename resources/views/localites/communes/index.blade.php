@extends('layouts.app')

@section('title', 'Communes | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-pin-map" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Localité</p>
        <h1 class="h3 mb-1">Communes</h1>
        <p class="text-muted mb-0">Gérez les communes, chacune rattachée à une province.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.regions.index') }}"><i class="bi bi-map" aria-hidden="true"></i> Régions</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.provinces.index') }}"><i class="bi bi-signpost-split" aria-hidden="true"></i> Provinces</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.villages.index') }}"><i class="bi bi-house-door" aria-hidden="true"></i> Villages</a>
      <a class="btn btn-primary btn-sm" href="{{ route('localites.communes.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une commune</a>
    </div>
  </div>


  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-pin-map" aria-hidden="true"></i><span>Liste des communes</span></h2>
        <p class="text-muted mb-0">{{ $communes->total() }} commune(s) enregistrée(s).</p>
      </div>
      <form class="d-flex flex-wrap gap-2" method="GET" action="{{ route('localites.communes.index') }}" data-cascade-filter>
        <select class="form-select form-select-sm" name="region_id" data-filter-region aria-label="Filtrer par région">
          <option value="">Toutes les régions</option>
          @foreach ($regions as $r)
            <option value="{{ $r->id }}" @selected((string) $regionId === (string) $r->id)>{{ $r->nom }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm" name="province_id" data-filter-province aria-label="Filtrer par province">
          <option value="">Toutes les provinces</option>
          @foreach ($provinces as $p)
            <option value="{{ $p->id }}" data-region-id="{{ $p->region_id }}" @selected((string) $provinceId === (string) $p->id)>{{ $p->nom }}</option>
          @endforeach
        </select>
        <input class="form-control form-control-sm" type="search" name="q" value="{{ $search }}" placeholder="Rechercher une commune" aria-label="Rechercher une commune">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search" aria-hidden="true"></i></button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Commune</th>
            <th scope="col">Province</th>
            <th scope="col">Région</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($communes as $commune)
            <tr>
              <td class="fw-semibold">{{ $commune->nom }}</td>
              <td>{{ $commune->province->nom }}</td>
              <td>{{ $commune->province->region->nom }}</td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a class="btn btn-light btn-sm" href="{{ route('localites.communes.edit', $commune) }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                  <form action="{{ route('localites.communes.destroy', $commune) }}" method="POST" data-confirm="Supprimer la commune « {{ $commune->nom }} » ?" data-confirm-danger>
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash" aria-hidden="true"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">Aucune commune trouvée.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($communes->hasPages())
      <div class="d-flex justify-content-center mt-3">
        {{ $communes->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>
@endsection
