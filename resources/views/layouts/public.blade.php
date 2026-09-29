<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="{{ $siteSetting->structure_description ?? $siteSetting->nom_site }}">
  <title>@yield('title', $siteSetting->ministere_tutelle ?? $siteSetting->nom_site)</title>

  {{-- Police du site public, réglable depuis Paramètres généraux > Apparence (voir config/fonts.php) --}}
  @if ($siteSetting->police_public_config['stylesheet'])
    <link rel="stylesheet" href="{{ asset($siteSetting->police_public_config['stylesheet']) }}">
  @endif

  <!-- Bootstrap 5 CSS & Icons -->
  <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/fontawesome/css/all.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">

  <!-- Custom Public CSS -->
  <link rel="stylesheet" href="{{ asset('assets/css/public.css') }}">
  {{-- {!! !!} : la valeur vient de la liste fermée config/fonts.php, jamais d'une saisie libre.
       Doit venir après public.css, qui définit la valeur par défaut de --public-font. --}}
  <style>:root { --public-font: {!! $siteSetting->police_public_config['family'] !!}; }</style>
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
          <span class="brand-title">{{ Str::upper($siteSetting->ministere_tutelle ?? $siteSetting->nom_site) }}</span>
          @if ($siteSetting->slogan)
            <span class="brand-subtitle">{{ $siteSetting->slogan }}</span>
          @endif
        </div>
      </a>

      <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbarContent" aria-controls="publicNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
        <i class="bi bi-list fs-2"></i>
      </button>

      <div class="collapse navbar-collapse" id="publicNavbarContent">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.home') ? 'active' : '' }}" href="{{ route('public.home') }}">
              <i class="bi bi-house-door"></i> Accueil
            </a>
          </li>
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.signaler*') ? 'active' : '' }}" href="{{ route('public.signaler') }}">
              <i class="bi bi-megaphone"></i> Signaler un OEV
            </a>
          </li>
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.suivi') ? 'active' : '' }}" href="{{ route('public.suivi') }}">
              <i class="bi bi-search"></i> Suivi du signalement
            </a>
          </li>
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.plainte') ? 'active' : '' }}" href="{{ route('public.plainte') }}">
              <i class="bi bi-exclamation-octagon"></i> Plainte
            </a>
          </li>
          <li class="nav-item">
            <a class="public-nav-link {{ request()->routeIs('public.about') ? 'active' : '' }}" href="{{ route('public.about') }}">
              <i class="bi bi-info-circle"></i> À propos
            </a>
          </li>
        </ul>

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
            <span>{{ $siteSetting->ministere_tutelle ?? $siteSetting->nom_site }}</span>
          </div>
          @if ($siteSetting->structure_description)
            <p class="footer-description">{{ $siteSetting->structure_description }}</p>
          @endif
        </div>

        <div class="col-6 col-lg-3">
          <h2 class="footer-title">Liens Rapides</h2>
          <ul class="footer-links">
            @foreach ($quickLinks as $link)
              <li><a href="{{ Str::startsWith($link->url, ['http://', 'https://', '#']) ? $link->url : url($link->url) }}"><i class="bi bi-chevron-right me-1"></i> {{ $link->libelle }}</a></li>
            @endforeach
          </ul>
        </div>

        <div class="col-6 col-lg-2">
          <h2 class="footer-title">Services</h2>
          <ul class="footer-links">
            @foreach ($services as $service)
              <li><a href="{{ Str::startsWith($service->url, ['http://', 'https://', '#']) ? $service->url : url($service->url) }}"><i class="bi bi-chevron-right me-1"></i> {{ $service->libelle }}</a></li>
            @endforeach
          </ul>
        </div>

        <div class="col-12 col-lg-3">
          <h2 class="footer-title">Contact & Localisation</h2>
          @if ($siteSetting->contact_adresse)
            <p class="small text-muted mb-2"><i class="bi bi-geo-alt-fill text-primary me-2"></i> {{ $siteSetting->contact_adresse }}</p>
          @endif
          @if ($siteSetting->contact_telephone)
            <p class="small text-muted mb-2"><i class="bi bi-telephone-fill text-primary me-2"></i> {{ $siteSetting->contact_telephone }}</p>
          @endif
          @if ($siteSetting->contact_email)
            <p class="small text-muted mb-0"><i class="bi bi-envelope-fill text-primary me-2"></i> {{ $siteSetting->contact_email }}</p>
          @endif
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
