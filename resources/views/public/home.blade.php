@extends('layouts.public')

@section('title', 'Ministère de la Famille et de la Solidarité')

@section('content')
<!-- Hero Section : diaporama plein écran en arrière-plan -->
<section class="hero-section hero-bg-slider">
  <!-- Images d'arrière-plan (défilement automatique) -->
  @php $slides = config('accueil.slides', []); @endphp
  <div id="heroCarousel" class="carousel slide carousel-fade hero-bg-carousel" data-bs-ride="carousel" data-bs-interval="5000" data-bs-pause="false">
    <div class="carousel-inner">
      @foreach ($slides as $slide)
        @php
          // Largeur de l'image pour ne garder qu'une partie de la bande de fond (cadrage gauche / droite)
          $largeur = null;
          if (isset($slide['fond_visible']) && in_array($slide['cadrage'] ?? '', ['gauche', 'droite'], true)
              && ($taille = @getimagesize(public_path($slide['image'])))) {
              $k = max(0, min(1, (float) $slide['fond_visible']));
              $ratio = $taille[0] / $taille[1];
              $largeur = sprintf('calc(%.1f%% + %.2fcqh)', (1 - $k) * 100, $k * $ratio * 100);
          }
        @endphp
        <div class="carousel-item hero-slide--{{ $slide['cadrage'] ?? 'plein' }} {{ ! empty($slide['decalage']) ? 'hero-slide--decale' : '' }} {{ $loop->first ? 'active' : '' }}"
             style="background: {{ $slide['fond'] ?? '#0a3a20' }}; --hero-img-hauteur: {{ $slide['hauteur'] ?? '100%' }}; --hero-img-position: {{ $slide['position'] ?? 'center' }};@if ($largeur) --hero-img-largeur: {{ $largeur }};@endif @if (! empty($slide['decalage'])) --hero-img-dx: {{ $slide['decalage']['x'] ?? '0%' }}; --hero-img-dy: {{ $slide['decalage']['y'] ?? '0%' }};@endif">
          <img class="hero-slide-img" src="{{ asset($slide['image']) }}" alt="{{ $slide['alt'] ?? '' }}" @unless ($loop->first) loading="lazy" @endunless>
          @if (! empty($slide['slogan']) && ($slide['slogan']['afficher'] ?? true))
            @php $slogan = $slide['slogan']; @endphp
            <div class="container px-3 px-lg-4 hero-slogan-wrap hero-slogan--{{ $slogan['horizontal'] ?? 'gauche' }} hero-slogan--{{ $slogan['vertical'] ?? 'milieu' }} hero-slogan--{{ $slogan['couleur'] ?? 'clair' }} {{ ! empty($slogan['style']) ? 'hero-slogan--'.$slogan['style'] : '' }} {{ ! empty($slogan['animation']) ? 'hero-slogan--anim-'.$slogan['animation'] : '' }}">
              <div class="hero-slogan" @if (! empty($slogan['taille_texte'])) style="--slogan-texte-echelle: {{ (float) $slogan['taille_texte'] }};" @endif>
                @if (! empty($slogan['titre']))<p class="hero-slogan-titre">{{ $slogan['titre'] }}</p>@endif
                @if (! empty($slogan['accent']))<p class="hero-slogan-accent"><span>{{ $slogan['accent'] }}</span></p>@endif
                @if (! empty($slogan['texte']))<p class="hero-slogan-texte {{ ! empty($slogan['couleur_texte']) ? 'hero-slogan-texte--'.$slogan['couleur_texte'] : '' }}">{{ $slogan['texte'] }}</p>@endif
                @if (! empty($slogan['icones']))
                  <div class="hero-slogan-icones">
                    @foreach ($slogan['icones'] as $icone)
                      <div class="hero-icone" style="--icone-couleur: {{ $icone['couleur'] ?? '#127a44' }};">
                        <span class="hero-icone-rond"><i class="{{ $icone['icone'] }}" aria-hidden="true"></i></span>
                        <span class="hero-icone-libelle">{{ $icone['libelle'] }}</span>
                      </div>
                    @endforeach
                  </div>
                @endif
                @if (! empty($slogan['ornement']))
                  <div class="hero-ornement" aria-hidden="true">
                    <span class="hero-ornement-rouge"></span><i class="fa-solid fa-star"></i><span class="hero-ornement-vert"></span>
                  </div>
                @endif
              </div>
            </div>
          @endif
        </div>
      @endforeach
    </div>
    <div class="carousel-indicators">
      @foreach ($slides as $slide)
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="{{ $loop->index }}" class="{{ $loop->first ? 'active' : '' }}" @if ($loop->first) aria-current="true" @endif aria-label="Image {{ $loop->iteration }}"></button>
      @endforeach
    </div>
  </div>

  <!-- Boutons d'action au premier plan -->
  <div class="container px-3 px-lg-4 hero-bg-content">
    <div class="hero-cta-group hero-cta-floating">
      <a href="{{ route('public.signaler') }}" class="btn-hero-primary btn-hero-animated">
        <i class="bi bi-megaphone-fill"></i> Signaler un OEV
      </a>
      <a href="{{ route('public.suivi') }}" class="btn-hero-secondary btn-hero-animated">
        <i class="bi bi-card-checklist"></i> Suivre mon signalement
      </a>
    </div>
  </div>
</section>

<section class="public-section public-section--light">
  <div class="container px-3 px-lg-4">
    <div class="section-title-wrapper reveal-up">
      <p class="section-eyebrow">Mission nationale</p>
      <h2 class="section-main-title">Un accompagnement au service de la protection de l’enfance</h2>
    </div>

    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-3 reveal-up" style="--reveal-delay: 0ms">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-book-half"></i>
          </div>
          <h3>Éducation</h3>
          <p>Appui à la scolarisation, aux fournitures scolaires et aux bourses pour garantir l’accès à l’éducation.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3 reveal-up" style="--reveal-delay: 120ms">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-heart-pulse"></i>
          </div>
          <h3>Santé</h3>
          <p>Suivi médical et accès aux soins pour assurer la santé physique et psychologique de chaque enfant.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3 reveal-up" style="--reveal-delay: 240ms">
        <article class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-people-fill"></i>
          </div>
          <h3>Protection sociale</h3>
          <p>Accompagnement psychosocial, évaluation des besoins et soutien des familles et tuteurs.</p>
        </article>
      </div>

      <div class="col-12 col-md-6 col-lg-3 reveal-up" style="--reveal-delay: 360ms">
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
    <div class="section-title-wrapper reveal-up">
      <p class="section-eyebrow">Parcours simple</p>
      <h2 class="section-main-title">Comment la demande est traitée ?</h2>
    </div>

    <div class="row g-4 justify-content-center">
      <div class="col-12 col-md-6 col-lg-3 reveal-up" style="--reveal-delay: 0ms">
        <div class="step-card">
          <span class="step-number">01</span>
          <h4>Identification</h4>
          <p>Enregistrement du dossier et vérification des informations de l’enfant et du tuteur.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3 reveal-up" style="--reveal-delay: 120ms">
        <div class="step-card">
          <span class="step-number">02</span>
          <h4>Déclaration</h4>
          <p>Soumission de la demande avec pièces justificatives et numéro de récépissé unique.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3 reveal-up" style="--reveal-delay: 240ms">
        <div class="step-card">
          <span class="step-number">03</span>
          <h4>Analyse</h4>
          <p>Vérification par les agents et validation des besoins selon les critères institutionnels.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3 reveal-up" style="--reveal-delay: 360ms">
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
        <a href="{{ route('public.signaler') }}" class="btn-hero-primary btn-hero-primary--compact">Signaler un OEV</a>
        <a href="{{ route('public.suivi') }}" class="btn-hero-secondary btn-hero-secondary--compact">Consulter le suivi</a>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  // Apparition des sections « Mission nationale » et « Parcours simple » au défilement
  (function () {
    const elements = document.querySelectorAll('.reveal-up');
    if (!('IntersectionObserver' in window)) {
      elements.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    elements.forEach(function (el) { observer.observe(el); });
  })();
</script>
@endpush
