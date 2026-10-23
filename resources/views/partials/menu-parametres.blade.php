{{-- Paramètres : sous-menu déplié sur les écrans de paramétrage --}}
@php
  $ecranParametres = request()->routeIs('parametres.*', 'localites.regions.*', 'localites.provinces.*', 'localites.communes.*', 'localites.villages.*');
  $liensParametres = [
    ['Paramètres généraux', 'bi-gear', route('parametres.edit'), request()->routeIs('parametres.edit')],
    ['Écoles', 'bi-building', route('parametres.ecoles.index'), request()->routeIs('parametres.ecoles.*')],
    ['Localités', 'bi-geo-alt', route('localites.regions.index'), request()->routeIs('localites.regions.*', 'localites.provinces.*', 'localites.communes.*', 'localites.villages.*')],
  ];
  foreach (\App\Models\ListeReference::listes() as $cleListe => $configListe) {
    $liensParametres[] = [$configListe['titre'], $configListe['icone'], route('parametres.listes.index', $cleListe), request()->routeIs('parametres.listes.*') && request()->route('liste') === $cleListe];
  }
  $liensParametres[] = ['Natures d’appui', 'bi-gift', route('parametres.natures-appui.index'), request()->routeIs('parametres.natures-appui.*')];
@endphp
<a class="nav-link nav-groupe {{ $ecranParametres ? '' : 'collapsed' }}" href="#menu-parametres" data-bs-toggle="collapse" role="button" aria-expanded="{{ $ecranParametres ? 'true' : 'false' }}" aria-controls="menu-parametres">
  <span class="nav-icon"><i class="bi bi-sliders" aria-hidden="true"></i></span>
  <span class="nav-text">Paramètres</span>
  <i class="bi bi-chevron-down nav-chevron nav-text" aria-hidden="true"></i>
</a>
<div class="collapse nav-sous-menu {{ $ecranParametres ? 'show' : '' }}" id="menu-parametres">
  @foreach ($liensParametres as [$libelleLien, $iconeLien, $urlLien, $actifLien])
    <a class="nav-link {{ $actifLien ? 'active' : '' }}" href="{{ $urlLien }}" @if ($actifLien) aria-current="page" @endif title="{{ $libelleLien }}">
      <span class="nav-icon"><i class="bi {{ $iconeLien }}" aria-hidden="true"></i></span>
      <span class="nav-text">{{ $libelleLien }}</span>
    </a>
  @endforeach
</div>
