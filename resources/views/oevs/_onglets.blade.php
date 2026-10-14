{{--
  Onglets de « Constituer dossier enfant » : dossiers en cours / dossiers intégrés / nouveau dossier.
  Variables facultatives : $nbEnCours, $nbIntegres (compteurs)
--}}
@php
  $surListe = request()->routeIs('oevs.index');
  $surIntegres = $surListe && request()->query('vue') === 'integres';
@endphp
<nav class="oev-onglets" aria-label="Constituer dossier enfant">
  @can('constituer dossiers')
    <a class="oev-onglet {{ $surListe && ! $surIntegres ? 'is-active' : '' }}" href="{{ route('oevs.index') }}" @if ($surListe && ! $surIntegres) aria-current="page" @endif>
      <i class="bi bi-folder2-open" aria-hidden="true"></i> Dossiers en cours
      @isset($nbEnCours)<span class="badge rounded-pill text-bg-light border ms-1">{{ $nbEnCours }}</span>@endisset
    </a>
    <a class="oev-onglet {{ $surIntegres ? 'is-active' : '' }}" href="{{ route('oevs.index', ['vue' => 'integres']) }}" @if ($surIntegres) aria-current="page" @endif>
      <i class="bi bi-person-check" aria-hidden="true"></i> Dossiers intégrés
      @isset($nbIntegres)<span class="badge rounded-pill text-bg-success ms-1">{{ $nbIntegres }}</span>@endisset
    </a>
  @endcan
  @can('constituer dossiers')
    <a class="oev-onglet {{ request()->routeIs('oevs.create') ? 'is-active' : '' }}" href="{{ route('oevs.create') }}" @if (request()->routeIs('oevs.create')) aria-current="page" @endif>
      <i class="bi bi-plus-lg" aria-hidden="true"></i> Nouveau dossier
    </a>
  @endcan
</nav>
