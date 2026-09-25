<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Plateforme OEV du Ministère de la Famille et de la Solidarité">
  <title>@yield('title', 'Ministère de la Famille et de la Solidarité')</title>

  <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/fontawesome/css/all.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
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
        <div class="nav-section">
          <span class="nav-section-label">Tableau de bord</span>
          <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" {{ request()->routeIs('dashboard') ? 'aria-current="page"' : '' }}>
            <span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
            <span class="nav-text">Dashboard</span>
          </a>
        </div>

        <div class="nav-section">
          <span class="nav-section-label">Gestion des demandes</span>
          <a class="nav-link" href="#">
            <span class="nav-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
            <span class="nav-text">Gestion des demandes</span>
          </a>
          <a class="nav-link" href="#">
            <span class="nav-icon"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i></span>
            <span class="nav-text">Constituer dossier enfant</span>
          </a>
          <a class="nav-link" href="#">
            <span class="nav-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
            <span class="nav-text">Validation enfant</span>
          </a>
        </div>

        <div class="nav-section">
          <span class="nav-section-label">OEV</span>
          <a class="nav-link" href="#">
            <span class="nav-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
            <span class="nav-text">Intégration des OEV</span>
          </a>
          <a class="nav-link" href="#">
            <span class="nav-icon"><i class="bi bi-map" aria-hidden="true"></i></span>
            <span class="nav-text">Suivi des OEV</span>
          </a>
        </div>

        <div class="nav-section">
          <span class="nav-section-label">Administration</span>
          <a class="nav-link {{ request()->routeIs('users.index') || request()->routeIs('users.create') || request()->routeIs('users.show') ? 'active' : '' }}" href="{{ route('users.index') }}">
            <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <span class="nav-text">Gestion utilisateur</span>
          </a>
          <a class="nav-link {{ request()->routeIs('roles-permissions.*') ? 'active' : '' }}" href="{{ route('roles-permissions.index') }}">
            <span class="nav-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
            <span class="nav-text">Gestion role et permission</span>
          </a>
          <a class="nav-link" href="#">
            <span class="nav-icon"><i class="bi bi-sliders" aria-hidden="true"></i></span>
            <span class="nav-text">Gestion des paramètres</span>
          </a>
        </div>

        <div class="nav-section">
          <span class="nav-section-label">Contrôle</span>
          <a class="nav-link" href="#">
            <span class="nav-icon"><i class="bi bi-chat-text" aria-hidden="true"></i></span>
            <span class="nav-text">Gestion de plainte</span>
          </a>
          <a class="nav-link" href="#">
            <span class="nav-icon"><i class="bi bi-funnel" aria-hidden="true"></i></span>
            <span class="nav-text">Filtrage et extraction</span>
          </a>
        </div>

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
                <a class="dropdown-item" href="{{ route('users.index') }}">
                  <span class="notification-title">New user registered</span>
                  <span class="notification-time">4 minutes ago</span>
                </a>
                <a class="dropdown-item" href="{{ route('roles-permissions.index') }}">
                  <span class="notification-title">Vérification des accès terminée</span>
                  <span class="notification-time">Il y a 32 minutes</span>
                </a>
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
  @stack('scripts')
</body>
</html>
