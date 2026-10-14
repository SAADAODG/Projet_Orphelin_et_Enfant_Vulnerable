{{--
  Onglets de « Liste des OEV » : OEV actifs / OEV sortants (désactivés et demandes de désactivation).
  Variables : $nbDesactives, $nbDemandes — un badge orange signale les demandes en attente de décision.
--}}
@php($surSortants = request()->routeIs('oevs.sortants'))
<nav class="oev-onglets" aria-label="Liste des OEV">
  <a class="oev-onglet {{ $surSortants ? '' : 'is-active' }}" href="{{ route('oevs.liste') }}" @unless ($surSortants) aria-current="page" @endunless>
    <i class="bi bi-person-lines-fill" aria-hidden="true"></i> OEV actifs
  </a>
  <a class="oev-onglet {{ $surSortants ? 'is-active' : '' }}" href="{{ route('oevs.sortants', $nbDemandes ? ['vue' => 'demandes'] : []) }}" @if ($surSortants) aria-current="page" @endif>
    <i class="bi bi-person-slash" aria-hidden="true"></i> OEV sortants
    <span class="badge rounded-pill text-bg-light border ms-1" title="OEV désactivés">{{ $nbDesactives }}</span>
    @if ($nbDemandes)
      <span class="badge rounded-pill text-bg-warning" title="Demandes de désactivation en attente de décision">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {{ $nbDemandes }} à traiter
      </span>
    @endif
  </a>
</nav>
