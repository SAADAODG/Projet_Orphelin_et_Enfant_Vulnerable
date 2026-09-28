@extends('layouts.app')

@use('App\Models\Oev')

@section('title', 'Liste des OEV | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@php
  $etatsDossier = [
    'complet' => 'Dossier complet',
    'incomplet' => 'Dossier incomplet',
    'aucun' => 'Sans dossier',
  ];
  $filtreActif = $filtres['dossier'] || $filtres['statut'] || $filtres['recherche'] !== '';
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">OEV</p>
        <h1 class="h3 mb-1">Liste des OEV</h1>
        <p class="text-muted mb-0">Orphelins et enfants vulnérables enregistrés. Cliquez sur un OEV pour voir sa fiche complète.</p>
      </div>
    </div>
    @can('enregistrer OEV')
    <div class="heading-actions">
      <a class="btn btn-primary" href="{{ route('oevs.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i> Enregistrer un OEV</a>
    </div>
    @endcan
  </div>

  @if (session('success')) <div class="alert alert-success mt-3"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> {{ session('success') }}</div> @endif

  <section class="row g-3 mt-1" aria-label="Statistiques des dossiers OEV">
    @foreach ([
      ['cle' => null, 'label' => 'OEV enregistrés', 'valeur' => $stats['total'], 'icone' => 'bi-people-fill', 'style' => ''],
      ['cle' => 'complet', 'label' => 'Dossiers complets', 'valeur' => $stats['complet'], 'icone' => 'bi-folder-check', 'style' => 'oev-stat--success'],
      ['cle' => 'incomplet', 'label' => 'Dossiers incomplets', 'valeur' => $stats['incomplet'], 'icone' => 'bi-folder2-open', 'style' => 'oev-stat--warning'],
      ['cle' => 'aucun', 'label' => 'Sans dossier', 'valeur' => $stats['aucun'], 'icone' => 'bi-folder-x', 'style' => 'oev-stat--danger'],
    ] as $carte)
      <div class="col-6 col-xl-3">
        <a class="oev-stat {{ $carte['style'] }} {{ $filtres['dossier'] === $carte['cle'] && ($carte['cle'] || ! $filtreActif) ? 'is-active' : '' }}"
           href="{{ route('oevs.index', array_filter(['dossier' => $carte['cle']])) }}">
          <span class="oev-stat-icone"><i class="bi {{ $carte['icone'] }}" aria-hidden="true"></i></span>
          <span>
            <span class="oev-stat-valeur d-block">{{ $carte['valeur'] }}</span>
            <span class="oev-stat-label">{{ $carte['label'] }}</span>
          </span>
        </a>
      </div>
    @endforeach
  </section>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1">OEV enregistrés <span class="badge rounded-pill text-bg-light border ms-1">{{ $oevs->total() }}</span></h2>
        <p class="text-muted mb-0">
          @if ($filtreActif)
            Résultats filtrés — <a href="{{ route('oevs.index') }}" class="text-decoration-none">tout afficher</a>
          @else
            Tous les OEV, du plus récent au plus ancien.
          @endif
        </p>
      </div>
    </div>

    <form class="oev-toolbar" method="GET" action="{{ route('oevs.index') }}" role="search">
      <div class="oev-recherche">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input class="form-control" name="q" type="search" value="{{ $filtres['recherche'] }}" placeholder="Rechercher par code, nom, prénom, tuteur, localité…" aria-label="Rechercher un OEV">
      </div>
      <select class="form-select" name="statut" aria-label="Filtrer par statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        @foreach (Oev::STATUTS as $cle => $label)
          <option value="{{ $cle }}" @selected($filtres['statut'] === $cle)>{{ $label }}</option>
        @endforeach
      </select>
      <select class="form-select" name="dossier" aria-label="Filtrer par état du dossier" onchange="this.form.submit()">
        <option value="">Tous les dossiers</option>
        @foreach ($etatsDossier as $cle => $label)
          <option value="{{ $cle }}" @selected($filtres['dossier'] === $cle)>{{ $label }}</option>
        @endforeach
      </select>
      <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Rechercher</button>
    </form>

    @if ($oevs->isEmpty())
      <div class="oev-vide">
        <div class="oev-vide-icone"><i class="bi {{ $filtreActif ? 'bi-search' : 'bi-people' }}" aria-hidden="true"></i></div>
        @if ($filtreActif)
          <h3 class="h5">Aucun OEV ne correspond à votre recherche</h3>
          <p class="text-muted">Essayez d’autres mots-clés ou retirez les filtres.</p>
          <a class="btn btn-outline-primary" href="{{ route('oevs.index') }}">Réinitialiser les filtres</a>
        @else
          <h3 class="h5">Aucun OEV enregistré pour le moment</h3>
          <p class="text-muted">Les OEV apparaîtront ici dès leur enregistrement.</p>
          @can('enregistrer OEV')
            <a class="btn btn-primary" href="{{ route('oevs.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i> Enregistrer le premier OEV</a>
          @endcan
        @endif
      </div>
    @else
      <div class="table-responsive">
        <table class="table align-middle mb-0 oev-liste">
          <thead>
            <tr><th>Code</th><th>Nom</th><th class="d-none d-md-table-cell">Prénom(s)</th><th>Statut</th><th class="text-end"><span class="visually-hidden">Ouvrir</span></th></tr>
          </thead>
          <tbody>
            @foreach ($oevs as $oev)
              <tr data-href="{{ route('oevs.show', $oev) }}">
                <td><a class="oev-code text-decoration-none" href="{{ route('oevs.show', $oev) }}" aria-label="Ouvrir la fiche de {{ $oev->nomComplet() }}">{{ $oev->code }}</a></td>
                <td>
                  <div class="d-flex align-items-center gap-3">
                    <span class="oev-avatar oev-avatar--{{ $oev->sexe }}" aria-hidden="true">{{ $oev->initiales() }}</span>
                    <span>
                      <span class="oev-nom d-block">{{ $oev->nom }}</span>
                      <span class="d-md-none small text-muted">{{ $oev->prenom }}</span>
                    </span>
                  </div>
                </td>
                <td class="d-none d-md-table-cell">{{ $oev->prenom }}</td>
                <td><span class="oev-statut oev-statut--{{ $oev->statut }}">{{ $oev->libelle('statut', Oev::STATUTS) }}</span></td>
                <td class="text-end"><span class="oev-ouvrir"><i class="bi bi-chevron-right" aria-hidden="true"></i></span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <small class="text-muted">Affichage de {{ $oevs->firstItem() }} à {{ $oevs->lastItem() }} sur {{ $oevs->total() }} OEV</small>
        {{ $oevs->links() }}
      </div>
    @endif
  </section>
</div>
@endsection

@push('scripts')
<script>
  // Toute la ligne ouvre la fiche de l'OEV (le code reste un vrai lien pour le clavier et le clic molette)
  document.querySelectorAll('.oev-liste tr[data-href]').forEach(function (ligne) {
    ligne.addEventListener('click', function (e) {
      if (e.target.closest('a')) { return; }
      if (e.ctrlKey || e.metaKey) { window.open(ligne.dataset.href, '_blank'); } else { window.location = ligne.dataset.href; }
    });
  });
</script>
@endpush
