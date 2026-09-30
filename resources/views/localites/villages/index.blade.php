@extends('layouts.app')

@section('title', 'Villages | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-house-door" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Localité</p>
        <h1 class="h3 mb-1">Villages / secteurs</h1>
        <p class="text-muted mb-0">Gérez les villages et secteurs, chacun rattaché à une commune.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.regions.index') }}"><i class="bi bi-map" aria-hidden="true"></i> Régions</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.provinces.index') }}"><i class="bi bi-signpost-split" aria-hidden="true"></i> Provinces</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('localites.communes.index') }}"><i class="bi bi-pin-map" aria-hidden="true"></i> Communes</a>
      <a class="btn btn-primary btn-sm" href="{{ route('localites.villages.create', array_filter(['commune_id' => $filtres['commune_id'] ?? null])) }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter un village</a>
    </div>
  </div>


  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-house-door" aria-hidden="true"></i><span>Liste des villages / secteurs</span></h2>
        <p class="text-muted mb-0">{{ $villages->total() }} village(s) / secteur(s) enregistré(s).</p>
      </div>
    </div>

    <form class="mb-3" method="GET" action="{{ route('localites.villages.index') }}">
      @include('partials.localite-selects', [
        'localites' => $localites,
        'valeurs' => $filtres,
        'requis' => false,
        'colonnes' => ['region_id' => 'col-md-3', 'province_id' => 'col-md-3', 'commune_id' => 'col-md-3'],
      ])
      <div class="d-flex flex-wrap gap-2 mt-2">
        <input class="form-control form-control-sm" style="max-width: 20rem" type="search" name="q" value="{{ $search }}" placeholder="Rechercher un village" aria-label="Rechercher un village">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Filtrer</button>
        @if ($search !== '' || array_filter($filtres))
          <a class="btn btn-link btn-sm" href="{{ route('localites.villages.index') }}">Réinitialiser</a>
        @endif
      </div>
    </form>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Village / secteur</th>
            <th scope="col">Commune</th>
            <th scope="col">Province</th>
            <th scope="col">Région</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($villages as $village)
            <tr>
              <td class="fw-semibold">{{ $village->nom }}</td>
              <td>{{ $village->commune->nom }}</td>
              <td>{{ $village->commune->province->nom }}</td>
              <td>{{ $village->commune->province->region->nom }}</td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a class="btn btn-light btn-sm" href="{{ route('localites.villages.edit', $village) }}" aria-label="Modifier"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                  <form action="{{ route('localites.villages.destroy', $village) }}" method="POST" data-confirm="Supprimer le village « {{ $village->nom }} » ?" data-confirm-danger>
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Supprimer"><i class="bi bi-trash" aria-hidden="true"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Aucun village trouvé.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($villages->hasPages())
      <div class="d-flex justify-content-center mt-3">
        {{ $villages->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>
@endsection
