@extends('layouts.app')

@section('title', 'Plaintes | Espace Agent OEV')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-chat-text" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Contrôle</p>
        <h1 class="h3 mb-1">Gestion des plaintes</h1>
        <p class="text-muted mb-0">Contributions, mécontentements, reconnaissances et appréciations des usagers sur la prise en charge des OEV.</p>
      </div>
    </div>
  </div>

  <section class="row g-3 mt-1" aria-label="Résumé des plaintes">
    @php
      $cartes = [
        ['label' => 'Total', 'valeur' => $total, 'classe' => 'metric-primary', 'icone' => 'bi-collection'],
        ['label' => 'Nouvelles', 'valeur' => $compteurs['nouvelle'] ?? 0, 'classe' => 'metric-danger', 'icone' => 'bi-envelope-exclamation'],
        ['label' => 'En cours', 'valeur' => $compteurs['en_cours'] ?? 0, 'classe' => 'metric-warning', 'icone' => 'bi-hourglass-split'],
        ['label' => 'Traitées', 'valeur' => $compteurs['traitee'] ?? 0, 'classe' => 'metric-success', 'icone' => 'bi-check2-circle'],
      ];
    @endphp
    @foreach ($cartes as $carte)
      <div class="col-12 col-sm-6 col-xl-3">
        <article class="metric-card {{ $carte['classe'] }}">
          <div class="metric-top">
            <span class="metric-label">{{ $carte['label'] }}</span>
            <span class="metric-icon"><i class="bi {{ $carte['icone'] }}" aria-hidden="true"></i></span>
          </div>
          <div class="metric-value">{{ $carte['valeur'] }}</div>
        </article>
      </div>
    @endforeach
  </section>

  <section class="panel mt-3">
    <div class="panel-header flex-wrap">
      <ul class="nav nav-pills statut-tabs gap-1">
        <li class="nav-item">
          <a class="nav-link {{ ! $statut ? 'active' : '' }}" href="{{ route('admin.plaintes.index', request()->only('q')) }}">Toutes</a>
        </li>
        @foreach (\App\Models\Plainte::STATUTS as $valeur => $libelle)
          <li class="nav-item">
            <a class="nav-link {{ $statut === $valeur ? 'active' : '' }}" href="{{ route('admin.plaintes.index', ['statut' => $valeur] + request()->only('q')) }}">
              {{ $libelle }} <span class="ms-1 opacity-75">({{ $compteurs[$valeur] ?? 0 }})</span>
            </a>
          </li>
        @endforeach
      </ul>
      <form method="GET" action="{{ route('admin.plaintes.index') }}" class="d-flex gap-2">
        @if ($statut)<input type="hidden" name="statut" value="{{ $statut }}">@endif
        <input class="form-control form-control-sm table-search" type="search" name="q" value="{{ request('q') }}" placeholder="Référence, mot-clé, lieu..." aria-label="Rechercher une plainte">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search" aria-hidden="true"></i></button>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Référence</th>
            <th scope="col">Motif</th>
            <th scope="col">Lieu</th>
            <th scope="col">Plaignant</th>
            <th scope="col">Reçue le</th>
            <th scope="col">Statut</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($plaintes as $plainte)
            <tr class="{{ $plainte->lu_at ? '' : 'signalement-row-new' }}">
              <td>
                <span class="fw-bold">{{ $plainte->reference }}</span>
                @unless ($plainte->lu_at)
                  <span class="badge text-bg-danger ms-1">Nouveau</span>
                @endunless
              </td>
              <td>
                <span class="badge text-bg-{{ \App\Models\Plainte::OBJETS_COULEURS[$plainte->objet] ?? 'secondary' }}"><i class="bi {{ \App\Models\Plainte::OBJETS_ICONES[$plainte->objet] ?? 'bi-chat' }} me-1" aria-hidden="true"></i>{{ $plainte->objet_libelle }}</span>
                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($plainte->description, 70) }}</div>
              </td>
              <td>{{ $plainte->province ?? '—' }}@if ($plainte->localite)<div class="small text-muted">{{ $plainte->localite }}</div>@endif</td>
              <td>
                @if ($plainte->anonyme || ! $plainte->nom)
                  <span class="text-muted"><i class="bi bi-incognito me-1" aria-hidden="true"></i>Anonyme</span>
                @else
                  {{ $plainte->nom }}
                @endif
                <div class="small {{ $plainte->peut_etre_contacte ? 'text-success' : 'text-muted' }}">
                  {{ $plainte->peut_etre_contacte ? 'Joignable' : 'Sans contact' }}
                </div>
              </td>
              <td class="text-nowrap">{{ $plainte->created_at->format('d/m/Y H:i') }}</td>
              <td>
                @if ($plainte->statut === 'traitee')
                  <span class="badge text-bg-success">Traitée</span>
                @elseif ($plainte->statut === 'en_cours')
                  <span class="badge text-bg-warning">En cours</span>
                @else
                  <span class="badge text-bg-danger">Nouvelle</span>
                @endif
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.plaintes.show', $plainte) }}">
                  <i class="bi bi-eye" aria-hidden="true"></i> Voir
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2" aria-hidden="true"></i>
                Aucune plainte pour le moment.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($plaintes->hasPages())
      <div class="mt-3">{{ $plaintes->links() }}</div>
    @endif
  </section>
</div>
@endsection
