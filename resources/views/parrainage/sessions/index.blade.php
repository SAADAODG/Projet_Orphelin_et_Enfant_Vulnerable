@extends('layouts.app')
@use('App\Models\SessionParrainage')
@use('App\Support\Montant')

@section('title', 'Sessions de parrainage | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-calendar2-range" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage</p>
        <h1 class="h3 mb-1">Sessions de parrainage</h1>
      </div>
    </div>
    @can('gérer sessions parrainage')
      <div class="heading-actions">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#nouvelleSessionModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouvelle session</button>
      </div>
    @endcan
  </div>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1">Liste des sessions <span class="badge rounded-pill text-bg-light border ms-1">{{ $sessions->total() }}</span></h2>
        <p class="text-muted mb-0">Cliquez sur une session pour ouvrir sa fiche et la liste de ses OEV.</p>
      </div>
      <form class="d-flex flex-wrap gap-2" method="GET" action="{{ route('parrainage.sessions.index') }}">
        <select class="form-select form-select-sm w-auto" name="annee" aria-label="Filtrer par année" onchange="this.form.submit()">
          <option value="">Toutes les années</option>
          @foreach ($annees as $annee)
            <option value="{{ $annee }}" @selected($filtres['annee'] === $annee)>{{ $annee }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm w-auto" name="type_appui" aria-label="Filtrer par type d’appui" onchange="this.form.submit()">
          <option value="">Tous les types</option>
          @foreach (SessionParrainage::TYPES_APPUI as $cle => $libelle)
            <option value="{{ $cle }}" @selected($filtres['type_appui'] === $cle)>{{ $libelle }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm w-auto" name="etat" aria-label="Filtrer par état" onchange="this.form.submit()">
          <option value="">Tous les états</option>
          @foreach (SessionParrainage::ETATS as $cle => $libelle)
            <option value="{{ $cle }}" @selected($filtres['etat'] === $cle)>{{ $libelle }}</option>
          @endforeach
        </select>
        @if (array_filter($filtres))
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.sessions.index') }}"><i class="bi bi-x-lg" aria-hidden="true"></i> Effacer</a>
        @endif
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0 oev-liste">
        <thead>
          <tr>
            <th scope="col">Année</th>
            <th scope="col">N°</th>
            <th scope="col">Description</th>
            <th scope="col">Type d’appui</th>
            <th scope="col" class="text-end">Enveloppe</th>
            <th scope="col" class="text-end">OEV retenus</th>
            <th scope="col">État</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($sessions as $session)
            <tr data-href="{{ route('parrainage.sessions.show', $session) }}">
              <td><span class="oev-code">{{ $session->annee }}</span></td>
              <td class="fw-semibold">n°{{ $session->numero }}</td>
              <td class="oev-nom">{{ $session->description }}</td>
              <td>{{ $session->libelleTypeAppui() }}</td>
              <td class="text-end text-nowrap">{{ $session->aUneEnveloppe() ? Montant::fcfa($session->enveloppe) : "—" }}</td>
              <td class="text-end">
                <a class="text-decoration-none" href="{{ route('parrainage.sessions.selection', ['session' => $session, 'onglet' => 'liste']) }}">{{ Montant::nombre($session->nb_retenus) }}</a>
              </td>
              <td><span class="badge rounded-pill text-bg-{{ $session->couleurEtat() }}">{{ $session->libelleEtat() }}</span></td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <a class="btn btn-light btn-sm" href="{{ route('parrainage.sessions.show', $session) }}" aria-label="Ouvrir la session {{ $session->reference() }}" title="Ouvrir"><i class="bi bi-eye" aria-hidden="true"></i></a>
                  @if ($session->parametresModifiables())
                    @can('gérer sessions parrainage')
                      <a class="btn btn-light btn-sm" href="{{ route('parrainage.sessions.selection', $session) }}" aria-label="Ajouter des OEV à la session {{ $session->reference() }}" title="Ajouter des OEV"><i class="bi bi-person-plus" aria-hidden="true"></i></a>
                    @endcan
                  @endif
                  @if ($session->peutEtreSupprimeePar(auth()->user()))
                    @include('parrainage.sessions._supprimer', ['session' => $session, 'compact' => true])
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Aucune session de parrainage.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($sessions->hasPages())
      <div class="d-flex justify-content-center mt-3">
        {{ $sessions->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>

@if ($formulaire)
  @include('parrainage._modale-creation', [
    'id' => 'nouvelleSessionModal',
    'titre' => 'Nouvelle session',
    'icone' => 'bi-calendar2-plus',
    'action' => route('parrainage.sessions.store'),
    'vue' => 'parrainage.sessions._form',
    'donnees' => $formulaire,
    'bouton' => 'Ouvrir la session',
    'taille' => 'modal-xl',
    'ouvrir' => old('_modale') === 'nouvelleSessionModal' || request()->boolean('nouveau'),
  ])
@endif
@endsection

@push('scripts')
<script>
  // Toute la ligne ouvre la session (les boutons gardent leur action)
  document.querySelectorAll('.oev-liste tr[data-href]').forEach(function (ligne) {
    ligne.addEventListener('click', function (e) {
      if (e.target.closest('a, button, form')) { return; }
      if (e.ctrlKey || e.metaKey) { window.open(ligne.dataset.href, '_blank'); } else { window.location = ligne.dataset.href; }
    });
  });
</script>
@endpush
