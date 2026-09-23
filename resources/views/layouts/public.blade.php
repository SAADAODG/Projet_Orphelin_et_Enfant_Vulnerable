<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Plateforme de prise en charge des Orphelins et Enfants Vulnérables (OEV)">
  <title>@yield('title', 'OEV | Plateforme de prise en charge')</title>

  <!-- Google Fonts Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 CSS & Icons -->
  <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">

  <!-- Custom Public CSS -->
  <link rel="stylesheet" href="{{ asset('assets/css/public.css') }}">
  @stack('styles')
</head>

<body class="public-body">
  <!-- Navbar Institutionnelle -->
  <nav class="navbar navbar-expand-lg public-navbar sticky-top">
    <div class="container px-3 px-lg-4">
      <a class="navbar-brand" href="{{ route('public.home') }}">
        <div class="brand-logo-icon bg-white p-1">
          <img src="{{ asset('assets/images/armoiries-1000x1174.png') }}" alt="Armoiries Officielles" style="height: 36px; width: auto; object-fit: contain;">
        </div>
        <div class="brand-text">
          <span class="brand-title">ORPHELINS ET ENFANTS VULNÉRABLES</span>
          <span class="brand-subtitle">Plateforme de prise en charge OEV</span>
        </div>
      </a>

      <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbarContent" aria-controls="publicNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
        <i class="bi bi-list fs-2"></i>
      </button>

      <div class="collapse navbar-collapse" id="publicNavbarContent">
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1">
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.home') ? 'active' : '' }}" href="{{ route('public.home') }}">
              <i class="bi bi-house-door"></i> Accueil
            </a>
          </li>
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.demande') ? 'active' : '' }}" href="{{ route('public.demande') }}">
              <i class="bi bi-file-earmark-plus"></i> Demande
            </a>
          </li>
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.suivi') ? 'active' : '' }}" href="{{ route('public.suivi') }}">
              <i class="bi bi-search"></i> Suivi
            </a>
          </li>
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.about') ? 'active' : '' }}" href="{{ route('public.about') }}">
              <i class="bi bi-info-circle"></i> À propos
            </a>
          </li>
        </ul>

        <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
          <a class="btn btn-auth-outline" href="{{ route('login') }}">
            <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
          </a>
          <a class="btn btn-auth-solid" href="{{ route('dashboard') }}">
            <i class="bi bi-person-badge me-1"></i> Espace Agent
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Contenu Principal -->
  <main>
    @yield('content')
  </main>

  <!-- Footer Institutionnel Dark -->
  <footer class="public-footer">
    <div class="container px-3 px-lg-4">
      <div class="row g-4">
        <div class="col-12 col-lg-4">
          <div class="footer-brand d-flex align-items-center gap-2">
            <img src="{{ asset('assets/images/armoiries-1000x1174.png') }}" alt="Armoiries du Burkina Faso" style="height: 38px; width: auto; object-fit: contain;">
            <span>OEV</span>
          </div>
          <p class="footer-description">
            Plateforme de recensement, de protection et de suivi des droits des orphelins et enfants vulnérables au Burkina Faso.
          </p>
        </div>

        <div class="col-6 col-lg-3">
          <h2 class="footer-title">Liens Rapides</h2>
          <ul class="footer-links">
            <li><a href="{{ route('public.demande') }}"><i class="bi bi-chevron-right me-1"></i> Faire une demande</a></li>
            <li><a href="{{ route('public.suivi') }}"><i class="bi bi-chevron-right me-1"></i> Suivre un récépissé</a></li>
            <li><a href="{{ route('public.about') }}"><i class="bi bi-chevron-right me-1"></i> Décrets & Éligibilité</a></li>
            <li><a href="{{ route('login') }}"><i class="bi bi-chevron-right me-1"></i> Connexion Agent</a></li>
          </ul>
        </div>

        <div class="col-6 col-lg-2">
          <h2 class="footer-title">Services</h2>
          <ul class="footer-links">
            <li><a href="#"><i class="bi bi-chevron-right me-1"></i> Prise en charge santé</a></li>
            <li><a href="#"><i class="bi bi-chevron-right me-1"></i> Bourses d'études</a></li>
            <li><a href="#"><i class="bi bi-chevron-right me-1"></i> Carte ou attestation OEV</a></li>
            <li><a href="#"><i class="bi bi-chevron-right me-1"></i> Support Usagers</a></li>
          </ul>
        </div>

        <div class="col-12 col-lg-3">
          <h2 class="footer-title">Contact & Localisation</h2>
          <p class="small text-muted mb-2"><i class="bi bi-geo-alt-fill text-primary me-2"></i> Ouagadougou, Burkina Faso</p>
          <p class="small text-muted mb-2"><i class="bi bi-telephone-fill text-primary me-2"></i> +226 25 30 00 00</p>
          <p class="small text-muted mb-0"><i class="bi bi-envelope-fill text-primary me-2"></i> contact@oev.gov.bf</p>
        </div>
      </div>

      <div class="footer-bottom text-center">
        <p class="mb-0">© {{ date('Y') }} Programme Orphelins et Enfants Vulnérables (OEV). Tous droits réservés.</p>
      </div>
    </div>
  </footer>

  <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
  @stack('scripts')
</body>
</html>
