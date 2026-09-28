{{-- Onglets de « Constituer dossier enfant » : dossiers constitués / nouveau dossier --}}
<nav class="oev-onglets" aria-label="Constituer dossier enfant">
  @can('constituer dossiers')
    <a class="oev-onglet {{ request()->routeIs('oevs.index') ? 'is-active' : '' }}" href="{{ route('oevs.index') }}" @if (request()->routeIs('oevs.index')) aria-current="page" @endif>
      <i class="bi bi-folder2-open" aria-hidden="true"></i> Dossiers enfants
    </a>
  @endcan
  @can('constituer dossiers')
    <a class="oev-onglet {{ request()->routeIs('oevs.create') ? 'is-active' : '' }}" href="{{ route('oevs.create') }}" @if (request()->routeIs('oevs.create')) aria-current="page" @endif>
      <i class="bi bi-plus-lg" aria-hidden="true"></i> Nouveau dossier
    </a>
  @endcan
</nav>
