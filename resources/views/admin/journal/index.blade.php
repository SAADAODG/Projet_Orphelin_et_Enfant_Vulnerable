@extends('layouts.app')
@use('App\Models\JournalActivite')
@use('App\Http\Controllers\JournalController')

@section('title', 'Journal d’audit | ' . $siteSetting->structure_nom)

@push('styles')
<style>
  .journal-module { display: inline-block; padding: .15rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 600;
    background: var(--admin-surface-soft); border: 1px solid var(--admin-border); white-space: nowrap; }
  .journal-module--auth { color: var(--admin-primary); }
  .journal-module--echec { color: var(--admin-danger); border-color: var(--admin-danger); }
  .journal-details { font-size: .8rem; color: var(--admin-muted); word-break: break-word; }
</style>
@endpush

@php($nbFiltres = count(array_filter($filtres)))

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Administration</p>
        <h1 class="h3 mb-1">Journal d’audit</h1>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-success btn-sm {{ $entrees->total() ? '' : 'disabled' }}" href="{{ route('journal.export', request()->query()) }}" @unless ($entrees->total()) aria-disabled="true" @endunless>
        <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel ({{ $entrees->total() }})
      </a>
    </div>
  </div>

  <section class="panel mt-3">
    <form class="row g-2 align-items-end" method="GET" action="{{ route('journal.index') }}">
      <div class="col-6 col-md-2">
        <label class="form-label small fw-semibold" for="du">Du</label>
        <input class="form-control form-control-sm" id="du" name="du" type="date" value="{{ $filtres['du'] }}">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small fw-semibold" for="au">Au</label>
        <input class="form-control form-control-sm" id="au" name="au" type="date" value="{{ $filtres['au'] }}">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold" for="utilisateur">Utilisateur</label>
        <select class="form-select form-select-sm" id="utilisateur" name="utilisateur">
          <option value="">Tous</option>
          @foreach ($utilisateurs as $utilisateur)
            <option value="{{ $utilisateur->id }}" @selected($filtres['utilisateur'] === $utilisateur->id)>{{ $utilisateur->name }} ({{ $utilisateur->email }})</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold" for="module">Module</label>
        <select class="form-select form-select-sm" id="module" name="module">
          <option value="">Tous</option>
          @foreach (JournalActivite::MODULES as $cle => $libelle)
            <option value="{{ $cle }}" @selected($filtres['module'] === $cle)>{{ $libelle }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold" for="q">Recherche</label>
        <input class="form-control form-control-sm" id="q" name="q" type="search" value="{{ $filtres['q'] }}" placeholder="Nom, dossier, session…">
      </div>
      <div class="col-12 d-flex justify-content-end gap-2">
        @if ($nbFiltres)<a class="btn btn-link btn-sm" href="{{ route('journal.index') }}">Réinitialiser</a>@endif
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
      </div>
    </form>
  </section>

  <section class="panel mt-3">
    <div class="panel-header">
      <h2 class="h5 mb-0">{{ $entrees->total() }} entrée(s)</h2>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col" class="text-nowrap">Date</th>
            <th scope="col">Utilisateur</th>
            <th scope="col">Module</th>
            <th scope="col">Action</th>
            <th scope="col" class="text-nowrap">Adresse IP</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($entrees as $entree)
            @php($details = JournalController::detailsEnTexte($entree->details))
            <tr>
              <td class="text-nowrap small">{{ $entree->created_at->format('d/m/Y') }}<span class="d-block text-muted">{{ $entree->created_at->format('H:i:s') }}</span></td>
              <td class="small">
                {{ $entree->utilisateur?->name ?? 'Système' }}
                @if ($entree->utilisateur)<span class="d-block text-muted">{{ $entree->utilisateur->email }}</span>@endif
              </td>
              <td>
                <span @class(['journal-module', 'journal-module--auth' => $entree->module() === 'auth', 'journal-module--echec' => $entree->action === 'auth.echec'])>{{ $entree->libelleModule() }}</span>
              </td>
              <td>
                {{ $entree->description }}
                @if ($details !== '')<span class="d-block journal-details">{{ $details }}</span>@endif
              </td>
              <td class="small text-muted text-nowrap">{{ $entree->details['ip'] ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Aucune action enregistrée{{ $nbFiltres ? ' pour ces filtres' : '' }}.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($entrees->hasPages())
      <div class="d-flex justify-content-center mt-3">
        {{ $entrees->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </section>
</div>
@endsection
