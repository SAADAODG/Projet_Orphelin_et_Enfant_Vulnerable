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
      </div>
    </div>
    @can('gérer parrains')
      <div class="heading-actions">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#nouveauParrainModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouveau parrain</button>
      </div>
    @endcan
  </div>

  <div class="mt-3">@include('parrainage.parrains._onglets')</div>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1">Répertoire des parrains <span class="badge rounded-pill text-bg-light border ms-1">{{ $parrains->total() }}</span></h2>
        <p class="text-muted mb-0">
          @if (array_filter($filtres, fn ($v) => $v !== null && $v !== ''))
            Résultats filtrés — <a href="{{ route('parrainage.parrains.index') }}" class="text-decoration-none">retirer les filtres</a>
          @else
            Cliquez sur un parrain pour ouvrir sa fiche (engagements, appuis).
          @endif
        </p>
      </div>
    </div>
      <form class="oev-toolbar" method="GET" action="{{ route('parrainage.parrains.index') }}" role="search">
        <div class="oev-recherche">
          <i class="bi bi-search" aria-hidden="true"></i>
          <input class="form-control" type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Nom ou contact" aria-label="Rechercher un parrain">
        </div>
        <select class="form-select" name="type" aria-label="Filtrer par type" onchange="this.form.submit()">
          <option value="">Tous les types</option>
          @foreach (Parrain::TYPES as $cle => $libelle)
            <option value="{{ $cle }}" @selected($filtres['type'] === $cle)>{{ $libelle }}</option>
          @endforeach
        </select>
        <select class="form-select" name="actif" aria-label="Filtrer par statut" onchange="this.form.submit()">
          <option value="">Actifs et inactifs</option>
          <option value="1" @selected($filtres['actif'] === '1')>Actifs</option>
          <option value="0" @selected($filtres['actif'] === '0')>Inactifs</option>
        </select>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Rechercher</button>
      </form>
    <div class="table-responsive">
      <table class="table align-middle mb-0 oev-liste">
        <thead>
          <tr>
            <th scope="col">Parrain</th>
            <th scope="col">Type</th>
            <th scope="col">Contact</th>
            <th scope="col" class="text-end">OEV appuyés</th>
            <th scope="col">Statut</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($parrains as $parrain)
            <tr @class(['text-muted' => ! $parrain->actif]) data-href="{{ route('parrainage.parrains.show', $parrain) }}">
              <td>
                <div class="d-flex align-items-center gap-3">
                  <span class="oev-avatar" aria-hidden="true">{{ mb_strtoupper(collect(preg_split('/\s+/u', trim($parrain->nom)))->take(2)->map(fn ($m) => mb_substr($m, 0, 1))->implode('')) }}</span>
                  <span class="oev-nom">{{ $parrain->nom }}</span>
                </div>
              </td>
              <td>{{ $parrain->libelleType() }}</td>
              <td>
                {{ $parrain->contact_nom ?? '—' }}
                @if ($parrain->telephone)<span class="d-block small text-muted">{{ $parrain->telephone }}</span>@endif
              </td>
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
              <td colspan="6" class="text-center text-muted py-4">Aucun parrain trouvé.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($parrains->total())
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <small class="text-muted">Affichage de {{ $parrains->firstItem() }} à {{ $parrains->lastItem() }} sur {{ $parrains->total() }} parrain(s)</small>
        {{ $parrains->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>

@if ($formulaire)
  @include('parrainage._modale-creation', [
    'id' => 'nouveauParrainModal',
    'titre' => 'Nouveau parrain',
    'icone' => 'bi-person-plus',
    'action' => route('parrainage.parrains.store'),
    'vue' => 'parrainage.parrains._form',
    'donnees' => $formulaire,
    'bouton' => 'Enregistrer le parrain',
    'ouvrir' => old('_modale') === 'nouveauParrainModal' || request()->boolean('nouveau'),
  ])
@endif
@endsection

@push('scripts')
<script>
  // Toute la ligne ouvre la fiche du parrain (les boutons gardent leur action)
  document.querySelectorAll('.oev-liste tr[data-href]').forEach(function (ligne) {
    ligne.addEventListener('click', function (e) {
      if (e.target.closest('a, button, form')) { return; }
      if (e.ctrlKey || e.metaKey) { window.open(ligne.dataset.href, '_blank'); } else { window.location = ligne.dataset.href; }
    });
  });
</script>
@endpush
