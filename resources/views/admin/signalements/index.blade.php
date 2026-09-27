@extends('layouts.app')

@section('title', 'Signalements | Espace Agent OEV')

@section('content')
@php use App\Models\Signalement; @endphp
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Prise en charge OEV</p>
        <h1 class="h3 mb-1">Signalements</h1>
        <p class="text-muted mb-0">Examinez, validez puis clôturez les signalements après le contact avec l'enfant.</p>
      </div>
    </div>
  </div>

  <!-- Compteurs compacts -->
  @php
    $cartes = [
      ['cle' => Signalement::EN_ATTENTE, 'label' => 'En attente', 'icone' => 'bi-hourglass-split', 'couleur' => 'warning'],
      ['cle' => Signalement::VALIDE, 'label' => 'Validés · contact à faire', 'icone' => 'bi-telephone-outbound', 'couleur' => 'primary'],
      ['cle' => Signalement::CLOTURE, 'label' => 'Clôturés', 'icone' => 'bi-check2-circle', 'couleur' => 'success'],
      ['cle' => Signalement::REJETE, 'label' => 'Rejetés', 'icone' => 'bi-x-circle', 'couleur' => 'danger'],
    ];
  @endphp
  <section class="sig-stats" aria-label="Résumé des signalements">
    @foreach ($cartes as $carte)
      <a class="sig-stat {{ $statut === $carte['cle'] ? 'is-active' : '' }}" href="{{ route('admin.signalements.index', ['statut' => $carte['cle']]) }}">
        <span class="sig-stat-icone text-{{ $carte['couleur'] }} bg-{{ $carte['couleur'] }}-subtle"><i class="bi {{ $carte['icone'] }}" aria-hidden="true"></i></span>
        <span>
          <span class="sig-stat-valeur">{{ $compteurs[$carte['cle']] ?? 0 }}</span>
          <span class="sig-stat-label">{{ $carte['label'] }}</span>
        </span>
      </a>
    @endforeach
  </section>

  <section class="sig-panel mt-3">
    <div class="sig-toolbar">
      <nav class="sig-tabs" aria-label="Filtrer par statut">
        <a class="sig-tab {{ ! $statut ? 'active' : '' }}" href="{{ route('admin.signalements.index', request()->only('q')) }}">Tous <span>{{ $total }}</span></a>
        @foreach (Signalement::STATUTS as $valeur => $libelle)
          <a class="sig-tab {{ $statut === $valeur ? 'active' : '' }}" href="{{ route('admin.signalements.index', ['statut' => $valeur] + request()->only('q')) }}">{{ $libelle }} <span>{{ $compteurs[$valeur] ?? 0 }}</span></a>
        @endforeach
      </nav>
      <form method="GET" action="{{ route('admin.signalements.index') }}" class="sig-search">
        @if ($statut)<input type="hidden" name="statut" value="{{ $statut }}">@endif
        @if ($decision)<input type="hidden" name="decision" value="{{ $decision }}">@endif
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Récépissé, nom, province…" aria-label="Rechercher un signalement">
      </form>
    </div>

    @if ($statut === Signalement::CLOTURE)
      <!-- Historique des clôtures : filtre par décision -->
      <div class="sig-subtabs">
        <span class="text-muted small me-1">Décision :</span>
        <a class="sig-chip {{ ! $decision ? 'active' : '' }}" href="{{ route('admin.signalements.index', ['statut' => Signalement::CLOTURE] + request()->only('q')) }}">Toutes</a>
        <a class="sig-chip sig-chip--success {{ $decision === Signalement::PRISE_EN_CHARGE ? 'active' : '' }}" href="{{ route('admin.signalements.index', ['statut' => Signalement::CLOTURE, 'decision' => Signalement::PRISE_EN_CHARGE] + request()->only('q')) }}"><i class="bi bi-house-heart" aria-hidden="true"></i> Prise en charge <span>{{ $decisions[Signalement::PRISE_EN_CHARGE] ?? 0 }}</span></a>
        <a class="sig-chip sig-chip--danger {{ $decision === Signalement::NON_PRISE_EN_CHARGE ? 'active' : '' }}" href="{{ route('admin.signalements.index', ['statut' => Signalement::CLOTURE, 'decision' => Signalement::NON_PRISE_EN_CHARGE] + request()->only('q')) }}"><i class="bi bi-slash-circle" aria-hidden="true"></i> Non prise en charge <span>{{ $decisions[Signalement::NON_PRISE_EN_CHARGE] ?? 0 }}</span></a>
      </div>
    @endif

    <div class="table-responsive">
      <table class="table sig-table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Enfant</th>
            <th scope="col">Situation</th>
            <th scope="col">Localité</th>
            <th scope="col">Déclarant</th>
            <th scope="col">{{ $statut === Signalement::CLOTURE ? 'Décision' : 'Statut' }}</th>
            <th scope="col" class="text-end">Reçu</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($signalements as $signalement)
            @php $url = route('admin.signalements.show', $signalement); @endphp
            <tr class="sig-row {{ $signalement->lu_at ? '' : 'is-new' }}" onclick="window.location='{{ $url }}'">
              <td>
                <div class="sig-enfant">
                  <span class="sig-avatar">{{ $signalement->initiales }}</span>
                  <span>
                    <a href="{{ $url }}" class="sig-nom">{{ $signalement->enfant_nom_complet }}</a>
                    @unless ($signalement->lu_at)<span class="sig-new-dot" title="Nouveau"></span>@endunless
                    <span class="sig-meta">{{ $signalement->enfant_age }} ans · {{ $signalement->recepisse }}</span>
                  </span>
                </div>
              </td>
              <td>
                @foreach (array_filter(explode(',', (string) $signalement->vulnerabilite)) as $cle)
                  <span class="sig-tag">{{ Signalement::VULNERABILITES[$cle] ?? $cle }}</span>
                @endforeach
              </td>
              <td>
                <span class="sig-lieu"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $signalement->province }}</span>
                <span class="sig-meta">{{ $signalement->localite }}</span>
              </td>
              <td>
                <span class="d-block">{{ $signalement->declarant_nom_complet }}</span>
                <span class="sig-meta">{{ $signalement->lien_libelle }}</span>
              </td>
              <td>
                @if ($signalement->statut === Signalement::CLOTURE)
                  <span class="sig-badge {{ $signalement->decision === Signalement::PRISE_EN_CHARGE ? 'text-success bg-success-subtle' : 'text-danger bg-danger-subtle' }}">
                    <i class="bi {{ $signalement->decision === Signalement::PRISE_EN_CHARGE ? 'bi-house-heart' : 'bi-slash-circle' }}" aria-hidden="true"></i>
                    {{ $signalement->decision_libelle }}
                  </span>
                  <span class="sig-meta">Clôturé le {{ $signalement->cloture_le?->format('d/m/Y') }}</span>
                @else
                  <span class="sig-badge text-{{ $signalement->statut_couleur }} bg-{{ $signalement->statut_couleur }}-subtle">{{ $signalement->statut_libelle }}</span>
                @endif
              </td>
              <td class="text-end text-nowrap">
                <span class="d-block small">{{ $signalement->created_at->format('d/m/Y') }}</span>
                <span class="sig-meta">{{ $signalement->created_at->diffForHumans() }}</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="sig-vide">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                <span>Aucun signalement {{ $statut ? 'dans cette catégorie' : 'pour le moment' }}.</span>
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
