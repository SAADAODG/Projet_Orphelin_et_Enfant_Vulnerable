{{-- Onglets de « Paramètres » : paramètres généraux / écoles --}}
<ul class="nav nav-tabs mt-3" aria-label="Paramètres">
  <li class="nav-item">
    <a class="nav-link {{ request()->routeIs('parametres.edit') ? 'active' : '' }}" href="{{ route('parametres.edit') }}" @if (request()->routeIs('parametres.edit')) aria-current="page" @endif>
      <i class="bi bi-gear" aria-hidden="true"></i> Paramètres généraux
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ request()->routeIs('parametres.ecoles.*') ? 'active' : '' }}" href="{{ route('parametres.ecoles.index') }}" @if (request()->routeIs('parametres.ecoles.*')) aria-current="page" @endif>
      <i class="bi bi-building" aria-hidden="true"></i> Écoles
    </a>
  </li>
</ul>
