@extends('layouts.app')

@section('title', 'Provinces | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-signpost-split" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Localité</p>
        <h1 class="h3 mb-1">Provinces</h1>
        <p class="text-muted mb-0">Gérez les provinces, chacune rattachée à une région.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.regions.index') }}"><i class="bi bi-map" aria-hidden="true"></i> Régions</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.communes.index') }}"><i class="bi bi-pin-map" aria-hidden="true"></i> Communes</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.villages.index') }}"><i class="bi bi-house-door" aria-hidden="true"></i> Villages</a>
      <a class="btn btn-primary btn-sm" href="{{ route('localites.provinces.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une province</a>
    </div>
  </div>


  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-signpost-split" aria-hidden="true"></i><span>Liste des provinces</span></h2>
        <p class="text-muted mb-0">{{ $provinces->total() }} province(s) enregistrée(s).</p>
      </div>
      <form class="d-flex flex-wrap gap-2" method="GET" action="{{ route('localites.provinces.index') }}">
        <select class="form-select form-select-sm" name="region_id" onchange="this.form.submit()" aria-label="Filtrer par région">
          <option value="">Toutes les régions</option>
          @foreach ($regions as $r)
            <option value="{{ $r->id }}" @selected((string) $regionId === (string) $r->id)>{{ $r->nom }}</option>
          @endforeach
        </select>
        <input class="form-control form-control-sm" type="search" name="q" value="{{ $search }}" placeholder="Rechercher une province" aria-label="Rechercher une province">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search" aria-hidden="true"></i></button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Province</th>
            <th scope="col">Région</th>
            <th scope="col">Chef-lieu</th>
            <th scope="col">Communes</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($provinces as $province)
            <tr>
              <td class="fw-semibold">{{ $province->nom }}</td>
              <td>{{ $province->region->nom }}</td>
              <td>{{ $province->chef_lieu ?? '—' }}</td>
              <td><span class="badge text-bg-light">{{ $province->communes_count }}</span></td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a class="btn btn-light btn-sm" href="{{ route('localites.provinces.edit', $province) }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                  <form action="{{ route('localites.provinces.destroy', $province) }}" method="POST" data-confirm="Supprimer la province « {{ $province->nom }} » ?" data-confirm-danger>
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash" aria-hidden="true"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Aucune province trouvée.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($provinces->hasPages())
      <div class="d-flex justify-content-center mt-3">
        {{ $provinces->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>
@endsection
