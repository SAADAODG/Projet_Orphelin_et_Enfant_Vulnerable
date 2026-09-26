@extends('layouts.app')

@section('title', 'Signalements | Espace Agent OEV')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Prise en charge OEV</p>
        <h1 class="h3 mb-1">Liste des signalements</h1>
        <p class="text-muted mb-0">Examinez les signalements envoyés par les citoyens, puis validez-les ou rejetez-les.</p>
      </div>
    </div>
  </div>

  <section class="row g-3 mt-1" aria-label="Résumé des signalements">
    @php
      $cartes = [
        ['label' => 'Total', 'valeur' => $total, 'classe' => 'metric-primary', 'icone' => 'bi-collection'],
        ['label' => 'En attente', 'valeur' => $compteurs['en_attente'] ?? 0, 'classe' => 'metric-warning', 'icone' => 'bi-hourglass-split'],
        ['label' => 'Validés', 'valeur' => $compteurs['valide'] ?? 0, 'classe' => 'metric-success', 'icone' => 'bi-check2-circle'],
        ['label' => 'Rejetés', 'valeur' => $compteurs['rejete'] ?? 0, 'classe' => 'metric-danger', 'icone' => 'bi-x-circle'],
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
          <a class="nav-link {{ ! $statut ? 'active' : '' }}" href="{{ route('admin.signalements.index', request()->only('q')) }}">Tous</a>
        </li>
        @foreach (\App\Models\Signalement::STATUTS as $valeur => $libelle)
          <li class="nav-item">
            <a class="nav-link {{ $statut === $valeur ? 'active' : '' }}" href="{{ route('admin.signalements.index', ['statut' => $valeur] + request()->only('q')) }}">
              {{ $libelle }} <span class="ms-1 opacity-75">({{ $compteurs[$valeur] ?? 0 }})</span>
            </a>
          </li>
        @endforeach
      </ul>
      <form method="GET" action="{{ route('admin.signalements.index') }}" class="d-flex gap-2">
        @if ($statut)<input type="hidden" name="statut" value="{{ $statut }}">@endif
        <input class="form-control form-control-sm table-search" type="search" name="q" value="{{ request('q') }}" placeholder="Récépissé, nom, province..." aria-label="Rechercher un signalement">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search" aria-hidden="true"></i></button>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Récépissé</th>
            <th scope="col">Enfant</th>
            <th scope="col">Vulnérabilité</th>
            <th scope="col">Localité</th>
            <th scope="col">Déclarant</th>
            <th scope="col">Reçu le</th>
            <th scope="col">Statut</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($signalements as $signalement)
            <tr class="{{ $signalement->lu_at ? '' : 'signalement-row-new' }}">
              <td>
                <span class="fw-bold">{{ $signalement->recepisse }}</span>
                @unless ($signalement->lu_at)
                  <span class="badge text-bg-danger ms-1">Nouveau</span>
                @endunless
              </td>
              <td>
                <div class="fw-semibold">{{ $signalement->enfant_nom_complet }}</div>
                <div class="small text-muted">{{ $signalement->enfant_age }} ans (estimé)</div>
              </td>
              <td>{{ $signalement->vulnerabilite_libelle }}</td>
              <td>
                <div>{{ $signalement->province }}</div>
                <div class="small text-muted">{{ $signalement->localite }}</div>
              </td>
              <td>
                <div>{{ $signalement->declarant_nom_complet }}</div>
                <div class="small text-muted">{{ $signalement->lien_libelle }}</div>
              </td>
              <td class="text-nowrap">{{ $signalement->created_at->format('d/m/Y H:i') }}</td>
              <td>
                @if ($signalement->statut === 'valide')
                  <span class="badge text-bg-success">Validé</span>
                @elseif ($signalement->statut === 'rejete')
                  <span class="badge text-bg-danger">Rejeté</span>
                @else
                  <span class="badge text-bg-warning">En attente</span>
                @endif
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.signalements.show', $signalement) }}">
                  <i class="bi bi-eye" aria-hidden="true"></i> Voir
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2" aria-hidden="true"></i>
                Aucun signalement pour le moment.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($signalements->hasPages())
      <div class="mt-3">{{ $signalements->links() }}</div>
    @endif
  </section>
</div>
@endsection
