<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Plateforme OEV du Ministère de la Famille et de la Solidarité">
  <title>@yield('title', 'Ministère de la Famille et de la Solidarité')</title>
  @include('partials.favicon')

  <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/fontawesome/css/all.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
  {{-- Police de l'interface admin, réglable depuis Paramètres généraux > Apparence (voir
       config/fonts.php) : {!! !!} car la valeur vient d'une liste fermée, jamais d'une saisie libre. --}}
  @if ($siteSetting->police_admin_config['stylesheet'])
    <link rel="stylesheet" href="{{ asset($siteSetting->police_admin_config['stylesheet']) }}">
  @endif
  <style>:root { --admin-font: {!! $siteSetting->police_admin_config['family'] !!}; }</style>
  @stack('styles')
</head>

<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="sidebar-header">
        <a class="brand-mark" href="{{ route('dashboard') }}" aria-label="Tableau de bord du Ministère de la Famille et de la Solidarité">
          <span class="brand-icon"><img src="{{ asset('assets/images/armoiries-1000x1174.png') }}" alt="Armoiries du Burkina Faso"></span>
          <span class="brand-copy">
            <span class="brand-title">Ministère de la Famille et de la Solidarité</span>
            <span class="brand-subtitle">Programme OEV</span>
          </span>
        </a>
      </div>

      <nav class="sidebar-nav">
        {{-- Chaque module n'apparaît que si le rôle de l'utilisateur y donne accès --}}
        <div class="nav-section">
          <span class="nav-section-label">Tableau de bord</span>
          <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" {{ request()->routeIs('dashboard') ? 'aria-current="page"' : '' }}>
            <span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
            <span class="nav-text">Tableau de bord</span>
          </a>
        </div>

        @php($ecranOev = match (true) {
            request()->routeIs('oevs.validation') => 'validation',
            request()->routeIs('oevs.integration') => 'integration',
            request()->routeIs('oevs.liste') => 'liste',
            request()->routeIs('oevs.*') => 'dossiers',
            default => null,
        })

        {{-- Du signalement au dossier validé : DP (province) et DR (région) --}}
        @canany(['voir signalements', 'constituer dossiers', 'valider dossiers'])
        <div class="nav-section">
          <span class="nav-section-label">Dossiers</span>
          @can('voir signalements')
          <a class="nav-link {{ request()->routeIs('admin.signalements.*') ? 'active' : '' }}" href="{{ route('admin.signalements.index') }}" @if (request()->routeIs('admin.signalements.*')) aria-current="page" @endif>
            <span class="nav-icon position-relative">
              <i class="bi bi-megaphone" aria-hidden="true"></i>
              <span class="notif-dot {{ $signalementsNonLus ? '' : 'd-none' }}" data-notif-signalements data-url="{{ route('admin.signalements.non-lus') }}" aria-label="{{ $signalementsNonLus }} nouveau(x) signalement(s)">{{ $signalementsNonLus > 99 ? '99+' : $signalementsNonLus }}</span>
            </span>
            <span class="nav-text">Signalements</span>
          </a>
          @endcan
          @can('constituer dossiers')
          <a class="nav-link {{ $ecranOev === 'dossiers' ? 'active' : '' }}" href="{{ route('oevs.index') }}" @if ($ecranOev === 'dossiers') aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i></span>
            <span class="nav-text">Constituer dossier enfant</span>
          </a>
          @endcan
          @can('valider dossiers')
          <a class="nav-link {{ $ecranOev === 'validation' ? 'active' : '' }}" href="{{ route('oevs.validation') }}" @if ($ecranOev === 'validation') aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-patch-check" aria-hidden="true"></i></span>
            <span class="nav-text">Validation des dossiers</span>
          </a>
          @endcan
        </div>
        @endcanany

        @canany(['intégrer OEV', 'voir OEV'])
        <div class="nav-section">
          <span class="nav-section-label">OEV</span>
          @can('intégrer OEV')
          <a class="nav-link {{ $ecranOev === 'integration' ? 'active' : '' }}" href="{{ route('oevs.integration') }}" @if ($ecranOev === 'integration') aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
            <span class="nav-text">Intégration des OEV</span>
          </a>
          @endcan
          @can('voir OEV')
          <a class="nav-link {{ $ecranOev === 'liste' ? 'active' : '' }}" href="{{ route('oevs.liste') }}" @if ($ecranOev === 'liste') aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
            <span class="nav-text">Liste des OEV</span>
          </a>
          {{-- « Suivi des OEV » : à réafficher quand l'écran sera réalisé --}}
          @endcan
        </div>
        @endcanany

        {{-- Parrainage : Sessions, Parrains, Pilotage ; Sélection et Suivi scolaire (module de Nakoulma) à venir --}}
        @can('voir parrainage')
        <div class="nav-section">
          <span class="nav-section-label">Parrainage</span>
          <a class="nav-link {{ request()->routeIs('parrainage.sessions.*') ? 'active' : '' }}" href="{{ route('parrainage.sessions.index') }}" @if (request()->routeIs('parrainage.sessions.*')) aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-calendar2-range" aria-hidden="true"></i></span>
            <span class="nav-text">Sessions</span>
          </a>
          @php($ecranParrains = request()->routeIs('parrainage.parrains.*', 'parrainage.appuis.*'))
          <a class="nav-link {{ $ecranParrains ? 'active' : '' }}" href="{{ route('parrainage.parrains.index') }}" @if ($ecranParrains) aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <span class="nav-text">Parrains</span>
          </a>
          <a class="nav-link {{ request()->routeIs('parrainage.pilotage.*') ? 'active' : '' }}" href="{{ route('parrainage.pilotage.index') }}" @if (request()->routeIs('parrainage.pilotage.*')) aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
            <span class="nav-text">Pilotage</span>
          </a>
        </div>
        @endcan

        @canany(['voir plaintes', 'voir rapports'])
        <div class="nav-section">
          <span class="nav-section-label">Contrôle</span>
          @can('voir plaintes')
          <a class="nav-link {{ request()->routeIs('admin.plaintes.*') ? 'active' : '' }}" href="{{ route('admin.plaintes.index') }}" @if (request()->routeIs('admin.plaintes.*')) aria-current="page" @endif>
            <span class="nav-icon position-relative">
              <i class="bi bi-chat-text" aria-hidden="true"></i>
              @if ($plaintesNonLues)
                <span class="notif-dot" aria-label="{{ $plaintesNonLues }} nouvelle(s) plainte(s)">{{ $plaintesNonLues > 99 ? '99+' : $plaintesNonLues }}</span>
              @endif
            </span>
            <span class="nav-text">Gestion de plainte</span>
          </a>
          @endcan
          @can('voir rapports')
          <a class="nav-link {{ request()->routeIs('extraction.*') ? 'active' : '' }}" href="{{ route('extraction.index') }}" @if (request()->routeIs('extraction.*')) aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-funnel" aria-hidden="true"></i></span>
            <span class="nav-text">Filtrage et extraction</span>
          </a>
          @endcan
        </div>
        @endcanany

        @if (auth()->user()->canAny(['voir utilisateurs', 'gérer paramètres']) || auth()->user()->supervise())
        <div class="nav-section">
          <span class="nav-section-label">Administration</span>
          @can('voir utilisateurs')
          <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}" @if (request()->routeIs('users.*')) aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <span class="nav-text">Gestion utilisateur</span>
          </a>
          @endcan
          @if (auth()->user()->supervise())
          <a class="nav-link {{ request()->routeIs('roles-permissions.*') ? 'active' : '' }}" href="{{ route('roles-permissions.index') }}" @if (request()->routeIs('roles-permissions.*')) aria-current="page" @endif>
            <span class="nav-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
            <span class="nav-text">Gestion role et permission</span>
          </a>
          @endif
          @can('gérer paramètres')
          <a class="nav-link {{ request()->routeIs('localites.*') ? 'active' : '' }}" href="{{ route('localites.regions.index') }}" {{ request()->routeIs('localites.*') ? 'aria-current="page"' : '' }}>
            <span class="nav-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
            <span class="nav-text">Localités</span>
          </a>
          <a class="nav-link {{ request()->routeIs('parametres.*') ? 'active' : '' }}" href="{{ route('parametres.edit') }}" {{ request()->routeIs('parametres.*') ? 'aria-current="page"' : '' }}>
            <span class="nav-icon"><i class="bi bi-sliders" aria-hidden="true"></i></span>
            <span class="nav-text">Paramètres généraux</span>
          </a>
          @endcan
        </div>
        @endif

        <a class="nav-link {{ request()->routeIs('profile') ? 'active' : '' }}" href="{{ route('profile') }}">
          <span class="nav-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
          <span class="nav-text">Profil</span>
        </a>
      </nav>

      <div class="sidebar-user">
        <img class="avatar-img avatar-md sidebar-user-avatar" src="{{ asset('assets/images/avatar/avatar.jpg') }}" alt="{{ auth()->user()->name }}">
        <strong>{{ auth()->user()->name }}</strong>
        <small>{{ auth()->user()->getRoleNames()->join(', ') ?: 'Utilisateur' }}</small>
      </div>

      <div class="sidebar-footer">
        <span class="status-dot"></span>
        <span class="sidebar-footer-text">System running smoothly</span>
      </div>
    </aside>

    <div class="admin-main">
      <nav class="navbar admin-navbar navbar-expand bg-white">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
            <span></span>
            <span></span>
            <span></span>
          </button>

          <form class="d-none d-md-flex ms-3 flex-grow-1" role="search">
            <input class="form-control search-input" type="search" placeholder="Search users, orders, reports" aria-label="Search">
          </form>

          <div class="navbar-actions ms-auto">
            <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
              <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
            </button>
            <div class="dropdown">
              <button class="icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <span class="notification-dot"></span>
                <i class="bi bi-bell" aria-hidden="true"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-end notification-menu">
                <div class="dropdown-header fw-bold text-body">Notifications</div>
                @can('voir utilisateurs')
                <a class="dropdown-item" href="{{ route('users.index') }}">
                  <span class="notification-title">New user registered</span>
                  <span class="notification-time">4 minutes ago</span>
                </a>
                @endcan
                @if (auth()->user()->supervise())
                <a class="dropdown-item" href="{{ route('roles-permissions.index') }}">
                  <span class="notification-title">Vérification des accès terminée</span>
                  <span class="notification-time">Il y a 32 minutes</span>
                </a>
                @endif
                <a class="dropdown-item" href="{{ route('settings') }}">
                  <span class="notification-title">Security review completed</span>
                  <span class="notification-time">1 hour ago</span>
                </a>
              </div>
            </div>

            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img class="avatar-img avatar-sm" src="{{ asset('assets/images/avatar/avatar.jpg') }}" alt="{{ auth()->user()->name }}">
                <span class="profile-name d-none d-sm-inline">{{ auth()->user()->name ?? 'Utilisateur' }}</span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile') }}">Profil</a></li>
                <li><a class="dropdown-item" href="{{ route('settings') }}">Paramètres du compte</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="dropdown-item text-start w-100 border-0 bg-transparent">Déconnexion</button>
                  </form>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        @yield('content')
      </main>

      <footer class="admin-footer">
        <div class="container-fluid px-3 px-lg-4">
          <span>Direction des Systèmes d’Informations du MFS</span>
          <span>Plateforme OEV</span>
        </div>
      </footer>
    </div>
  </div>

  <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('assets/js/main.js') }}"></script>
  <script>
    // Rafraîchit la pastille rouge des nouveaux signalements toutes les 30 secondes
    (function () {
      const pastille = document.querySelector('[data-notif-signalements]');
      if (!pastille) return;
      setInterval(function () {
        fetch(pastille.dataset.url, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            pastille.textContent = data.count > 99 ? '99+' : data.count;
            pastille.classList.toggle('d-none', data.count === 0);
            pastille.setAttribute('aria-label', data.count + ' nouveau(x) signalement(s)');
          })
          .catch(function () {});
      }, 30000);
    })();
  </script>
  @include('partials.alertes')
  @stack('scripts')
</body>
</html>
