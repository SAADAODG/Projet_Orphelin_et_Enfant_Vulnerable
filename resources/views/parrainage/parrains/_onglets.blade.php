{{-- Vues du sous-onglet « Parrains » : répertoire / appuis enregistrés --}}
@php($surAppuis = request()->routeIs('parrainage.appuis.*'))
<nav class="oev-onglets" aria-label="Parrains">
  <a class="oev-onglet {{ $surAppuis ? '' : 'is-active' }}" href="{{ route('parrainage.parrains.index') }}" @unless ($surAppuis) aria-current="page" @endunless>
    <i class="bi bi-person-vcard" aria-hidden="true"></i> Répertoire des parrains
  </a>
  <a class="oev-onglet {{ $surAppuis ? 'is-active' : '' }}" href="{{ route('parrainage.appuis.index') }}" @if ($surAppuis) aria-current="page" @endif>
    <i class="bi bi-gift" aria-hidden="true"></i> Appuis enregistrés
  </a>
</nav>
