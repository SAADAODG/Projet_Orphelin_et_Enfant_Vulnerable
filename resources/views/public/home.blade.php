@extends('layouts.public')

@section('title', 'Accueil | Plateforme OEV')

@section('content')
<!-- Hero Section (Inspiré de la maquette ONEA) -->
<section class="hero-section">
  <div class="container px-3 px-lg-4">
    <div class="row align-items-center g-5">
      <div class="col-12 col-lg-7">
        <h1 class="hero-title">
          Plateforme Officielle de <br>
          <span class="highlight-green">prise en charge OEV</span>
        </h1>
        <p class="hero-subtitle">
          Orphelins et Enfants Vulnérables (OEV). <br><br>
          Effectuez vos demandes de prise en charge en ligne, transmettez vos pièces justificatives et suivez l'évolution de vos dossiers en temps réel.
        </p>
        <div class="hero-cta-group">
          <a href="{{ route('public.demande') }}" class="btn-hero-primary">
            <i class="bi bi-file-earmark-plus-fill"></i> Faire une Demande
          </a>
          <a href="{{ route('public.suivi') }}" class="btn-hero-secondary">
            <i class="bi bi-card-checklist"></i> Suivre mon Dossier
          </a>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="hero-visual-container">
          <!-- Bulles décoratives (Effet Glassmorphism) -->
          <div class="glass-bubble bubble-1"></div>
          <div class="glass-bubble bubble-2"></div>

          <!-- Carte Flottante du Sceau / Logo -->
          <div class="hero-floating-card">
            <div class="hero-emblem-badge bg-white p-2">
              <img src="{{ asset('assets/images/armoiries-1000x1174.png') }}" alt="Armoiries du Burkina Faso" style="max-height: 90px; width: auto; object-fit: contain;">
            </div>
            <h2 class="hero-card-title">OEV</h2>
            <p class="hero-card-sub">ORPHELINS ET ENFANTS VULNÉRABLES</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Section Piliers de Prise en Charge -->
<section class="public-section bg-white">
  <div class="container px-3 px-lg-4">
    <div class="section-title-wrapper">
      <p class="section-eyebrow">Accompagnement & Protection</p>
      <h2 class="section-main-title">Piliers de prise en charge des OEV</h2>
    </div>

    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-3">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-journal-bookmark-fill"></i>
          </div>
          <h3>Éducation & Scolarité</h3>
          <p>Prise en charge des frais de scolarité, fourniture de kits scolaires et octroi de bourses d'études de l'enseignement primaire au supérieur.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-heart-pulse-fill"></i>
          </div>
          <h3>Couverture Médicale</h3>
          <p>Accès gratuit et prioritaire aux soins de santé, consultations médicales spécialisées et prise en charge des ordonnances.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-people-fill"></i>
          </div>
          <h3>Accompagnement Social</h3>
          <p>Assistance psychosociale aux familles et tuteurs, enquêtes de terrain et protection des droits des enfants sur tout le territoire.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-person-vcard-fill"></i>
          </div>
          <h3>Attestation OEV</h3>
          <p>Délivrance d'une attestation de prise en charge facilitant l'accès aux services sociaux et aux programmes dédiés.</p>
        </article>
      </div>
    </div>
  </div>
</section>

<!-- Section Parcours Utilisateur (Basé sur le Diagramme d'Activité) -->
<section class="public-section bg-light">
  <div class="container px-3 px-lg-4">
    <div class="section-title-wrapper">
      <p class="section-eyebrow">Procédure Simplifiée</p>
      <h2 class="section-main-title">Comment s'effectue la prise en charge ?</h2>
    </div>

    <div class="row g-4 text-center">
      <div class="col-12 col-md-3">
        <div class="p-4 bg-white rounded-4 shadow-sm h-100">
          <div class="badge bg-primary rounded-circle mb-3 p-3 fs-4" style="width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center;">1</div>
          <h4 class="h6 fw-bold text-dark">Identification de l'enfant</h4>
          <p class="small text-muted mb-0">Enregistrement du dossier de l'enfant et vérification des pièces justificatives.</p>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="p-4 bg-white rounded-4 shadow-sm h-100">
          <div class="badge bg-primary rounded-circle mb-3 p-3 fs-4" style="width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center;">2</div>
          <h4 class="h6 fw-bold text-dark">Initiation de la Demande</h4>
          <p class="small text-muted mb-0">L'usager/tuteur effectue la demande en ligne et reçoit son N° de récépissé unique.</p>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="p-4 bg-white rounded-4 shadow-sm h-100">
          <div class="badge bg-primary rounded-circle mb-3 p-3 fs-4" style="width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center;">3</div>
          <h4 class="h6 fw-bold text-dark">Vérification & Validation</h4>
          <p class="small text-muted mb-0">Instruction du dossier par l'agent social et validation par le responsable habilité.</p>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="p-4 bg-white rounded-4 shadow-sm h-100">
          <div class="badge bg-success rounded-circle mb-3 p-3 fs-4" style="width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center;">4</div>
          <h4 class="h6 fw-bold text-dark">Émission de la Carte</h4>
          <p class="small text-muted mb-0">Notification de la décision et activation des mesures de prise en charge adaptées.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Section Chiffres Clés -->
<section class="public-section bg-primary text-white py-5">
  <div class="container px-3 px-lg-4 text-center">
    <div class="row g-4">
      <div class="col-6 col-md-3">
        <div class="display-5 fw-bold mb-1">1 240+</div>
        <div class="text-white-50 small text-uppercase">Enfants accompagnés</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="display-5 fw-bold mb-1">98%</div>
        <div class="text-white-50 small text-uppercase">Demandes Traitées</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="display-5 fw-bold mb-1">45</div>
        <div class="text-white-50 small text-uppercase">Provinces Couvertes</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="display-5 fw-bold mb-1">24h/48h</div>
        <div class="text-white-50 small text-uppercase">Délai Moyen d'Instruction</div>
      </div>
    </div>
  </div>
</section>
@endsection
