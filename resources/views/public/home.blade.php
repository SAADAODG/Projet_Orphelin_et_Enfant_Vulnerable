@extends('layouts.public')

@section('title', 'Ministère de la Famille et de la Solidarité')

@section('content')
<section class="hero-section">
  <div class="container px-3 px-lg-4">
    <div class="row align-items-center g-5">
      <div class="col-12 col-lg-7">
        <div class="hero-badges">
          <span class="badge-pill badge-pill--soft">Burkina Faso</span>
          <span class="badge-pill badge-pill--gold">Ministère de la Famille et de la Solidarité</span>
        </div>

        <h1 class="hero-title">
          Ministère de la Famille et de la Solidarité <br>
          <span class="highlight-green">Protection, accompagnement et prise en charge</span>
          des orphelins et enfants vulnérables.
        </h1>

        <p class="hero-subtitle">
          La plateforme OEV accompagne les orphelins et enfants vulnérables dans leur accès à la protection sociale,
          à l’éducation, à la santé et à l’accompagnement institutionnel, dans le respect de la dignité humaine et des valeurs nationales.
        </p>

        <div class="hero-cta-group">
          <a href="{{ route('public.demande') }}" class="btn-hero-primary">
            <i class="bi bi-file-earmark-plus-fill"></i> Faire une demande
          </a>
          <a href="{{ route('public.suivi') }}" class="btn-hero-secondary">
            <i class="bi bi-search"></i> Suivre mon dossier
          </a>
        </div>

        <div class="hero-trust-row">
          <div class="trust-item">
            <strong>+1 200</strong>
            <span>Enfants accompagnés</span>
          </div>
          <div class="trust-item">
            <strong>98%</strong>
            <span>Demandes traitées</span>
          </div>
          <div class="trust-item">
            <strong>24h</strong>
            <span>Réponse rapide</span>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="hero-visual-container">
          <div class="hero-panel">
            <div class="hero-panel__header">
              <div class="mini-emblem">
                <img src="{{ asset('assets/images/armoiries-1000x1174.png') }}" alt="Armoiries du Burkina Faso">
              </div>
              <div>
                <span class="mini-label">République du Burkina Faso</span>
                <h2>OEV</h2>
              </div>
            </div>

            <div class="hero-panel__stats">
              <div class="stat-box stat-box--green">
                <span class="stat-number">03</span>
                <span class="stat-label">Piliers</span>
              </div>
              <div class="stat-box stat-box--gold">
                <span class="stat-number">100%</span>
                <span class="stat-label">Dignité</span>
              </div>
            </div>

            <div class="hero-panel__list">
              <div class="info-line">
                <i class="bi bi-shield-check"></i>
                <span>Protection des enfants</span>
              </div>
              <div class="info-line">
                <i class="bi bi-book-half"></i>
                <span>Éducation et développement</span>
              </div>
              <div class="info-line">
                <i class="bi bi-heart-pulse"></i>
                <span>Santé et accompagnement social</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="public-section public-section--light">
  <div class="container px-3 px-lg-4">
    <div class="section-title-wrapper">
      <p class="section-eyebrow">Mission nationale</p>
      <h2 class="section-main-title">Un accompagnement au service de la protection de l’enfance</h2>
    </div>

    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-3">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-book-half"></i>
          </div>
          <h3>Éducation</h3>
          <p>Appui à la scolarisation, aux fournitures scolaires et aux bourses pour garantir l’accès à l’éducation.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-heart-pulse"></i>
          </div>
          <h3>Santé</h3>
          <p>Suivi médical et accès aux soins pour assurer la santé physique et psychologique de chaque enfant.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-people-fill"></i>
          </div>
          <h3>Protection sociale</h3>
          <p>Accompagnement psychosocial, évaluation des besoins et soutien des familles et tuteurs.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-file-earmark-check"></i>
          </div>
          <h3>Suivi administratif</h3>
          <p>Instruction claire des dossiers, notifications et suivi fiable depuis la demande jusqu’à la décision.</p>
        </article>
      </div>
    </div>
  </div>
</section>

<section class="public-section">
  <div class="container px-3 px-lg-4">
    <div class="section-title-wrapper">
      <p class="section-eyebrow">Parcours simple</p>
      <h2 class="section-main-title">Comment la demande est traitée ?</h2>
    </div>

    <div class="row g-4 justify-content-center">
      <div class="col-12 col-md-6 col-lg-3">
        <div class="step-card">
          <span class="step-number">01</span>
          <h4>Identification</h4>
          <p>Enregistrement du dossier et vérification des informations de l’enfant et du tuteur.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="step-card">
          <span class="step-number">02</span>
          <h4>Déclaration</h4>
          <p>Soumission de la demande avec pièces justificatives et numéro de récépissé unique.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="step-card">
          <span class="step-number">03</span>
          <h4>Analyse</h4>
          <p>Vérification par les agents et validation des besoins selon les critères institutionnels.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="step-card step-card--success">
          <span class="step-number">04</span>
          <h4>Décision</h4>
          <p>Notification de la décision et activation du plan de prise en charge adapté.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="public-section public-section--stats">
  <div class="container px-3 px-lg-4">
    <div class="stats-banner">
      <div class="row align-items-center g-4 text-center text-lg-start">
        <div class="col-12 col-lg-4">
          <p class="section-eyebrow section-eyebrow--light">Impact</p>
          <h2 class="section-main-title section-main-title--light">Un engagement citoyen au service des enfants</h2>
        </div>

        <div class="col-6 col-md-3">
          <div class="stat-pill">
            <strong>45</strong>
            <span>Provinces couvertes</span>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-pill">
            <strong>98%</strong>
            <span>Traitement des dossiers</span>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-pill">
            <strong>24h</strong>
            <span>Réception de la demande</span>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-pill">
            <strong>1 200+</strong>
            <span>Cas accompagnés</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="public-section public-section--cta">
  <div class="container px-3 px-lg-4">
    <div class="cta-panel">
      <div>
        <p class="section-eyebrow">Appel à la solidarité</p>
        <h2 class="section-main-title">Vous êtes parent, tuteur ou acteur de terrain ?</h2>
      </div>
      <div class="cta-actions">
        <a href="{{ route('public.demande') }}" class="btn-hero-primary btn-hero-primary--compact">Déposer une demande</a>
        <a href="{{ route('public.suivi') }}" class="btn-hero-secondary btn-hero-secondary--compact">Consulter le suivi</a>
      </div>
    </div>
  </div>
</section>
@endsection
