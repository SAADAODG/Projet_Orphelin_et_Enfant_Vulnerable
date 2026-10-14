@extends('layouts.app')

@use('App\Models\Ecole')
@use('App\Http\Controllers\EcoleController')

@section('title', 'Écoles | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Configuration</p>
        <h1 class="h3 mb-1">Paramètres</h1>
        <p class="text-muted mb-0">Écoles proposées dans le formulaire OEV, selon la commune de l’enfant.</p>
      </div>
    </div>
    <div class="heading-actions">
      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#import-ecoles" aria-expanded="{{ $errors->has('fichier') ? 'true' : 'false' }}" aria-controls="import-ecoles">
        <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i> Importer un fichier Excel
      </button>
      <a class="btn btn-primary btn-sm" href="{{ route('parametres.ecoles.create', array_filter(['commune_id' => $filtres['commune_id'] ?? null])) }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une école</a>
    </div>
  </div>

  @include('parametres._onglets')

  <section class="panel mt-3 collapse {{ $errors->has('fichier') ? 'show' : '' }}" id="import-ecoles">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i><span>Importer la structure des écoles</span></h2>
        <p class="text-muted mb-0">
          Première ligne = en-tête, puis une école par ligne avec les colonnes, dans cet ordre :
          <strong>{{ implode(' · ', EcoleController::COLONNES_IMPORT) }}</strong>.
          Les nouvelles écoles sont ajoutées ; celles déjà connues (même commune, CEB et nom) voient leur statut et leur type mis à jour.
        </p>
      </div>
    </div>
    <form class="d-flex flex-wrap align-items-start gap-2" method="POST" action="{{ route('parametres.ecoles.import') }}" enctype="multipart/form-data">
      @csrf
      <div style="min-width: 16rem; flex: 1 1 20rem; max-width: 32rem">
        <input class="form-control @error('fichier') is-invalid @enderror" type="file" name="fichier" accept=".xlsx,.xls,.csv" required aria-label="Fichier Excel des écoles">
        @error('fichier')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <button class="btn btn-primary" type="submit"><i class="bi bi-upload" aria-hidden="true"></i> Importer</button>
    </form>
  </section>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-building" aria-hidden="true"></i><span>Liste des écoles</span></h2>
        <p class="text-muted mb-0">{{ number_format($ecoles->total(), 0, ',', ' ') }} école(s) trouvée(s).</p>
      </div>
    </div>

    <form class="mb-3" method="GET" action="{{ route('parametres.ecoles.index') }}">
      @include('partials.localite-selects', [
        'localites' => $localites,
        'valeurs' => $filtres,
        'requis' => false,
        'colonnes' => ['region_id' => 'col-md-4', 'province_id' => 'col-md-4', 'commune_id' => 'col-md-4'],
      ])
      <div class="d-flex flex-wrap gap-2 mt-2">
        <input class="form-control form-control-sm" style="max-width: 20rem" type="search" name="q" value="{{ $search }}" placeholder="Rechercher une école ou une CEB" aria-label="Rechercher une école ou une CEB">
        <select class="form-select form-select-sm" style="max-width: 12rem" name="statut" aria-label="Statut">
          <option value="">Tous les statuts</option>
          @foreach (Ecole::STATUTS as $cle => $libelle)<option value="{{ $cle }}" @selected(($filtres['statut'] ?? '') === $cle)>{{ $libelle }}</option>@endforeach
        </select>
        <select class="form-select form-select-sm" style="max-width: 12rem" name="type" aria-label="Type">
          <option value="">Tous les types</option>
          @foreach (Ecole::TYPES as $cle => $libelle)<option value="{{ $cle }}" @selected(($filtres['type'] ?? '') === $cle)>{{ $libelle }}</option>@endforeach
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Filtrer</button>
        @if ($search !== '' || array_filter($filtres))
          <a class="btn btn-link btn-sm" href="{{ route('parametres.ecoles.index') }}">Réinitialiser</a>
        @endif
      </div>
    </form>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">École</th>
            <th scope="col">CEB</th>
            <th scope="col">Statut</th>
            <th scope="col">Type</th>
            <th scope="col">Commune</th>
            <th scope="col">Province</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($ecoles as $ecole)
            <tr>
              <td class="fw-semibold">{{ $ecole->nom }}</td>
              <td>{{ $ecole->ceb ?? '—' }}</td>
              <td>{{ $ecole->libelleStatut() ?? '—' }}</td>
              <td>{{ $ecole->libelleType() ?? '—' }}</td>
              <td>{{ $ecole->commune->nom }}</td>
              <td>{{ $ecole->commune->province->nom }}</td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a class="btn btn-light btn-sm" href="{{ route('parametres.ecoles.edit', $ecole) }}" aria-label="Modifier"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                  <form action="{{ route('parametres.ecoles.destroy', $ecole) }}" method="POST" data-confirm="Supprimer l’école « {{ $ecole->nom }} » ?" data-confirm-danger>
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Supprimer"><i class="bi bi-trash" aria-hidden="true"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Aucune école trouvée.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($ecoles->hasPages())
      <div class="d-flex justify-content-center mt-3">
        {{ $ecoles->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>
@endsection
