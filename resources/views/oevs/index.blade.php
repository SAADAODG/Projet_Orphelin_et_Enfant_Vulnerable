@extends('layouts.app')

@use('App\Models\Oev')

@section('title', 'Liste des OEV | OEV')

@php
  $etatsDossier = [
    'complet' => ['label' => 'Dossier complet', 'couleur' => 'success'],
    'incomplet' => ['label' => 'Dossier incomplet', 'couleur' => 'warning'],
    'aucun' => ['label' => 'Sans dossier', 'couleur' => 'secondary'],
  ];
  $totalPieces = count(Oev::DOCUMENTS);
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">OEV</p>
        <h1 class="h3 mb-1">Liste des OEV</h1>
        <p class="text-muted mb-0">Orphelins et enfants vulnérables enregistrés, avec ou sans dossier.</p>
      </div>
    </div>
    @can('enregistrer OEV')
    <div class="heading-actions">
      <a class="btn btn-primary btn-sm" href="{{ route('oevs.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i> Enregistrer un OEV</a>
    </div>
    @endcan
  </div>

  @if (session('success')) <div class="alert alert-success mt-3">{{ session('success') }}</div> @endif

  <section class="row g-3 mt-1" aria-label="Statistiques des dossiers OEV">
    @foreach ([
      ['cle' => null, 'label' => 'Total OEV', 'valeur' => $stats['total'], 'icone' => 'bi-people', 'style' => 'metric-primary'],
      ['cle' => 'complet', 'label' => 'Dossiers complets', 'valeur' => $stats['complet'], 'icone' => 'bi-folder-check', 'style' => 'metric-success'],
      ['cle' => 'incomplet', 'label' => 'Dossiers incomplets', 'valeur' => $stats['incomplet'], 'icone' => 'bi-folder2-open', 'style' => 'metric-warning'],
      ['cle' => 'aucun', 'label' => 'Sans dossier', 'valeur' => $stats['aucun'], 'icone' => 'bi-folder-x', 'style' => 'metric-danger'],
    ] as $carte)
      <div class="col-12 col-sm-6 col-xl-3">
        <a class="text-decoration-none" href="{{ route('oevs.index', array_filter(['dossier' => $carte['cle']])) }}">
          <article class="metric-card {{ $carte['style'] }} {{ $filtres['dossier'] === $carte['cle'] ? 'border border-2 border-primary' : '' }}">
            <div class="metric-top">
              <span class="metric-label">{{ $carte['label'] }}</span>
              <span class="metric-icon"><i class="bi {{ $carte['icone'] }}" aria-hidden="true"></i></span>
            </div>
            <div class="metric-value">{{ $carte['valeur'] }}</div>
          </article>
        </a>
      </div>
    @endforeach
  </section>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1">OEV enregistrés</h2>
        <p class="text-muted mb-0">{{ $oevs->total() }} OEV trouvé(s).</p>
      </div>
    </div>

    <form class="row g-2 align-items-end mb-3" method="GET" action="{{ route('oevs.index') }}">
      <div class="col-12 col-md-5">
        <label class="form-label small mb-1" for="filtre-q">Recherche</label>
        <input class="form-control form-control-sm" id="filtre-q" name="q" type="search" value="{{ $filtres['recherche'] }}" placeholder="Code, nom, tuteur, localité, établissement">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="filtre-dossier">Dossier</label>
        <select class="form-select form-select-sm" id="filtre-dossier" name="dossier">
          <option value="">Tous</option>
          @foreach ($etatsDossier as $cle => $etat)
            <option value="{{ $cle }}" @selected($filtres['dossier'] === $cle)>{{ $etat['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="filtre-statut">Statut</label>
        <select class="form-select form-select-sm" id="filtre-statut" name="statut">
          <option value="">Tous</option>
          @foreach (Oev::STATUTS as $cle => $label)
            <option value="{{ $cle }}" @selected($filtres['statut'] === $cle)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('oevs.index') }}" title="Réinitialiser" aria-label="Réinitialiser les filtres"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr><th>Code</th><th>Enfant</th><th>Statut</th><th>Tuteur</th><th>Scolarité en cours</th><th>Localité</th><th>Dossier</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          @forelse ($oevs as $oev)
            @php($etat = $oev->etatDossier())
            <tr>
              <td><strong>{{ $oev->code }}</strong></td>
              <td>
                {{ $oev->nomComplet() }}
                @if ($oev->handicap)<span class="badge text-bg-info ms-1" title="{{ $oev->nature_handicap }}">Handicap</span>@endif
                <br><small class="text-muted">{{ $oev->sexe === 'F' ? 'Fille' : 'Garçon' }} · né(e) le {{ $oev->date_naissance->format('d/m/Y') }} ({{ $oev->age() }} ans)</small>
              </td>
              <td><small>{{ $oev->libelle('statut', Oev::STATUTS) }}</small></td>
              <td>{{ $oev->nomCompletTuteur() }}<br><small class="text-muted">{{ $oev->contact_tuteur }}</small></td>
              <td>{{ $oev->etablissement_actuel }}<br><small class="text-muted">{{ $oev->classe }} · {{ $oev->libelle('type_etablissement', Oev::TYPES_ETABLISSEMENT) }}</small></td>
              <td><small>{{ $oev->commune }}<br><span class="text-muted">{{ $oev->province }}, {{ $oev->region }}</span></small></td>
              <td>
                <span class="badge text-bg-{{ $etatsDossier[$etat]['couleur'] }}">{{ $etatsDossier[$etat]['label'] }}</span>
                <small class="text-muted d-block">{{ $oev->documents_count }}/{{ $totalPieces }} pièce(s)</small>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-light" href="{{ route('oevs.show', $oev) }}" title="Voir la fiche" aria-label="Voir la fiche de {{ $oev->nomComplet() }}"><i class="fa-solid fa-eye" aria-hidden="true"></i></a>
                  @can('enregistrer OEV')
                    <a class="btn btn-outline-primary" href="{{ route('oevs.edit', $oev) }}" title="{{ $etat === 'complet' ? 'Modifier' : 'Compléter le dossier' }}" aria-label="Modifier {{ $oev->nomComplet() }}"><i class="fa-solid {{ $etat === 'complet' ? 'fa-pen-to-square' : 'fa-folder-plus' }}" aria-hidden="true"></i></a>
                  @endcan
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Aucun OEV ne correspond à ces critères.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="d-flex justify-content-end mt-3">{{ $oevs->links() }}</div>
  </section>
</div>
@endsection
