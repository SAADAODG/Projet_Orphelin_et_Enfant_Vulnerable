@extends('layouts.app')
@use('App\Models\SessionParrainage')
@use('App\Support\Montant')

@section('title', 'Sessions de parrainage | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-calendar2-range" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage</p>
        <h1 class="h3 mb-1">Sessions de parrainage</h1>
        <p class="text-muted mb-0">Chaque session fixe l’enveloppe, le plafond par bénéficiaire et les règles de sélection d’une année scolaire.</p>
      </div>
    </div>
    @can('gérer sessions parrainage')
      <div class="heading-actions">
        <a class="btn btn-primary btn-sm" href="{{ route('parrainage.sessions.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouvelle session</a>
      </div>
    @endcan
  </div>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-list-ul" aria-hidden="true"></i><span>Liste des sessions</span></h2>
        <p class="text-muted mb-0">{{ $sessions->total() }} session(s).</p>
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
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Année</th>
            <th scope="col">N°</th>
            <th scope="col">Description</th>
            <th scope="col">Type d’appui</th>
            <th scope="col" class="text-end">Enveloppe</th>
            <th scope="col">État</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($sessions as $session)
            <tr>
              <td class="fw-semibold">{{ $session->annee }}</td>
              <td>{{ $session->numero }}</td>
              <td>{{ $session->description }}</td>
              <td>{{ $session->libelleTypeAppui() }}</td>
              <td class="text-end text-nowrap">{{ Montant::fcfa($session->enveloppe) }}</td>
              <td><span class="badge rounded-pill text-bg-{{ $session->couleurEtat() }}">{{ $session->libelleEtat() }}</span></td>
              <td class="text-end">
                <a class="btn btn-light btn-sm" href="{{ route('parrainage.sessions.show', $session) }}" aria-label="Ouvrir la session {{ $session->reference() }}"><i class="bi bi-eye" aria-hidden="true"></i></a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Aucune session de parrainage.</td>
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
@endsection
