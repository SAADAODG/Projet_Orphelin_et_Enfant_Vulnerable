{{-- Vues du sous-onglet « Pilotage » : tableau de bord / extractions --}}
@php($surExtractions = request()->routeIs('parrainage.pilotage.extractions'))
<nav class="oev-onglets" aria-label="Pilotage">
  <a class="oev-onglet {{ $surExtractions ? '' : 'is-active' }}" href="{{ route('parrainage.pilotage.index') }}" @unless ($surExtractions) aria-current="page" @endunless>
    <i class="bi bi-speedometer2" aria-hidden="true"></i> Tableau de bord
  </a>
  <a class="oev-onglet {{ $surExtractions ? 'is-active' : '' }}" href="{{ route('parrainage.pilotage.extractions') }}" @if ($surExtractions) aria-current="page" @endif>
    <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Extractions
  </a>
</nav>
