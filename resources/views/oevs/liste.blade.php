@extends('layouts.app')

@use('App\Models\Oev')

@section('title', 'Liste des OEV | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@php
  $filtreActif = $filtres['statut'] || $filtres['recherche'] !== '';
  $iconesStatuts = [
    'orphelin_pere' => 'bi-person-dash',
    'orphelin_mere' => 'bi-person-dash',
    'orphelin_double' => 'bi-people',
    'vulnerable' => 'bi-shield-exclamation',
  ];
  $cartes = collect([['cle' => null, 'label' => 'Total OEV', 'valeur' => $compteurs->sum(), 'icone' => 'bi-person-check']])
    ->concat(collect(Oev::STATUTS)->map(fn ($label, $cle) => ['cle' => $cle, 'label' => $label, 'valeur' => $compteurs[$cle], 'icone' => $iconesStatuts[$cle]])->values());
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">OEV</p>
        <h1 class="h3 mb-1">Liste des OEV</h1>
        <p class="text-muted mb-0">Enfants intégrés au niveau central et enregistrés comme OEV dans la base de données.</p>
      </div>
    </div>
  </div>

  <section class="row g-3 mt-1 oev-stats--compact" aria-label="OEV par statut">
    @foreach ($cartes as $carte)
      <div class="col-6 col-md-4 col-xxl">
        <a class="oev-stat {{ $carte['cle'] ? 'oev-stat--' . $carte['cle'] : 'oev-stat--success' }} {{ $filtres['statut'] === $carte['cle'] ? 'is-active' : '' }}"
           href="{{ route('oevs.liste', array_filter(['statut' => $carte['cle'], 'q' => $filtres['recherche'] ?: null])) }}"
           @if ($filtres['statut'] === $carte['cle']) aria-current="true" @endif>
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
        <h2 class="h5 mb-1">
          {{ $filtres['statut'] ? Oev::STATUTS[$filtres['statut']] : 'Tous les OEV' }}
          <span class="badge rounded-pill text-bg-light border ms-1">{{ $oevs->total() }}</span>
        </h2>
        <p class="text-muted mb-0">
          @if ($filtreActif)
            Résultats filtrés — <a href="{{ route('oevs.liste') }}" class="text-decoration-none">tout afficher</a>
          @else
            Du plus récemment intégré au plus ancien. Cliquez sur un OEV pour voir sa fiche complète.
          @endif
        </p>
      </div>
    </div>

    <form class="oev-toolbar" method="GET" action="{{ route('oevs.liste') }}" role="search">
      @if ($filtres['statut'])<input type="hidden" name="statut" value="{{ $filtres['statut'] }}">@endif
      <div class="oev-recherche">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input class="form-control" name="q" type="search" value="{{ $filtres['recherche'] }}" placeholder="Code OEV, nom, prénom, tuteur, localité, établissement…" aria-label="Rechercher un OEV">
      </div>
      <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Rechercher</button>
    </form>

    @if ($oevs->isEmpty())
      <div class="oev-vide">
        <div class="oev-vide-icone"><i class="bi {{ $filtreActif ? 'bi-search' : 'bi-person-check' }}" aria-hidden="true"></i></div>
        @if ($filtreActif)
          <h3 class="h5">Aucun OEV ne correspond à votre recherche</h3>
          <p class="text-muted">Essayez d’autres mots-clés ou retirez les filtres.</p>
        @else
          <h3 class="h5">Aucun OEV intégré pour le moment</h3>
          <p class="text-muted">Un enfant apparaît ici dès que son dossier, validé par le DR, est intégré au niveau central.</p>
        @endif
      </div>
    @else
      <div class="table-responsive">
        <table class="table align-middle mb-0 oev-liste">
          <thead>
            <tr>
              <th>Code OEV</th><th>Nom</th><th class="d-none d-md-table-cell">Prénom(s)</th><th>Statut</th>
              <th class="d-none d-lg-table-cell">Localité</th><th class="d-none d-lg-table-cell">Intégré le</th>
              <th class="text-end"><span class="visually-hidden">Ouvrir</span></th>
            </tr>
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
                <td class="d-none d-lg-table-cell small text-nowrap" title="{{ $oev->commune }}, {{ $oev->province }}, {{ $oev->region }}">{{ $oev->commune }} <span class="text-muted">· {{ $oev->province }}</span></td>
                <td class="d-none d-lg-table-cell small text-nowrap">{{ $oev->integre_at?->format('d/m/Y') }}</td>
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
  document.querySelectorAll('.oev-liste tr[data-href]').forEach(function (ligne) {
    ligne.addEventListener('click', function (e) {
      if (e.target.closest('a')) { return; }
      if (e.ctrlKey || e.metaKey) { window.open(ligne.dataset.href, '_blank'); } else { window.location = ligne.dataset.href; }
    });
  });
</script>
@endpush
