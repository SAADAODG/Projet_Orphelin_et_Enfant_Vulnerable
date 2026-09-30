@extends('layouts.public')

@section('title', 'Plainte | OEV')

@section('content')
<div class="bg-light py-3 border-bottom">
  <div class="container px-3 px-lg-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('public.home') }}" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">Plainte</li>
      </ol>
    </nav>
    <h1 class="h4 fw-bold text-dark mb-1">Exprimez-vous sur la prise en charge des OEV</h1>
    <p class="text-muted small mb-0">Une idée, un mécontentement, un remerciement ou un avis : votre parole nous aide à faire avancer la prise en charge des enfants. Vous pouvez rester anonyme.</p>
  </div>
</div>

<section class="public-section signal-section">
  <div class="container px-3 px-lg-4">
    <div class="row g-4 justify-content-center">
      <div class="col-12 col-lg-8">

        <form action="{{ route('public.plainte.store') }}" method="POST" class="signal-form" novalidate>
          @csrf

          <!-- Votre message -->
          <div class="signal-step">
            <div class="signal-step-header">
              <span class="signal-step-icon"><i class="bi bi-chat-heart" aria-hidden="true"></i></span>
              <div>
                <h2 class="signal-step-title">Que souhaitez-vous nous dire ?</h2>
                <p class="signal-step-sub">Choisissez le motif, puis exprimez-vous avec vos mots.</p>
              </div>
            </div>

            <div class="motif-grille" role="radiogroup" aria-label="Motif du message">
              @foreach ($objets as $valeur => $libelle)
                <label class="motif">
                  <input type="radio" name="objet" value="{{ $valeur }}" {{ old('objet') === $valeur ? 'checked' : '' }} required>
                  <span class="motif-corps">
                    <i class="bi {{ $icones[$valeur] }}" aria-hidden="true"></i>
                    <span>
                      <span class="motif-titre">{{ $libelle }}</span>
                      <span class="motif-desc">{{ $descriptions[$valeur] }}</span>
                    </span>
                  </span>
                </label>
              @endforeach
            </div>
            @error('objet')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <div class="mt-3">
              <label class="form-label" for="description">Votre message</label>
              <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="5" maxlength="5000" placeholder="Décrivez votre idée, la situation vécue, ou ce que vous avez apprécié…" required>{{ old('description') }}</textarea>
              @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <!-- Contact : rien n'est demandé si la personne reste anonyme -->
          <div class="signal-step">
            <div class="signal-step-header">
              <span class="signal-step-icon"><i class="bi bi-incognito" aria-hidden="true"></i></span>
              <div>
                <h2 class="signal-step-title">Rester anonyme ?</h2>
                <p class="signal-step-sub">Si vous restez anonyme, aucune information ne vous sera demandée.</p>
              </div>
            </div>

            @php $anonyme = session()->hasOldInput() ? (bool) old('anonyme') : true; @endphp
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="anonyme" name="anonyme" value="1" {{ $anonyme ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold small" for="anonyme">Je souhaite rester anonyme</label>
            </div>

            <div id="blocContact" class="mt-3 {{ $anonyme ? 'd-none' : '' }}">
              <p class="small text-muted mb-2">Laissez vos coordonnées pour que nous puissions vous répondre ou vous demander des précisions. Elles restent confidentielles.</p>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="nom">Nom et prénom(s)</label>
                  <input type="text" class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" value="{{ old('nom') }}">
                  @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="telephone">Téléphone</label>
                  <input type="tel" class="form-control @error('telephone') is-invalid @enderror" id="telephone" name="telephone" value="{{ old('telephone') }}" placeholder="ex : 70 00 00 00">
                  @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="email">E-mail</label>
                  <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="ex : nom@exemple.com">
                  @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-2">
            <a href="{{ route('public.home') }}" class="btn btn-outline-secondary px-4">Annuler</a>
            <button type="submit" class="btn btn-primary px-4 fw-bold">
              <i class="bi bi-send-fill me-2"></i> Envoyer mon message
            </button>
          </div>
        </form>
      </div>

      <div class="col-12 col-lg-4">
        <div class="signal-aside">
          <div class="signal-howto-card mb-3">
            <h3 class="h6 fw-bold text-dark mb-2"><i class="bi bi-shield-lock text-primary me-2"></i> Votre anonymat est respecté</h3>
            <p class="small text-muted mb-0">Seuls le motif et votre message sont obligatoires. Les informations transmises sont réservées aux agents chargés de la prise en charge des OEV.</p>
          </div>

          <a class="help-discret" href="tel:+22625300000">
            <span class="help-discret-icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
            <span>
              <span class="help-discret-titre">Un enfant en danger ?</span>
              <span class="help-discret-numero">+226 25 30 00 00</span>
              <span class="help-discret-info">Appel gratuit · 8h – 16h · ou <u>Signaler un OEV</u></span>
            </span>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    // Anonyme : on masque et on vide toutes les coordonnées
    const anonyme = document.getElementById('anonyme');
    const blocContact = document.getElementById('blocContact');
    anonyme.addEventListener('change', function () {
      blocContact.classList.toggle('d-none', anonyme.checked);
      if (anonyme.checked) {
        blocContact.querySelectorAll('input').forEach(function (champ) { champ.value = ''; });
      }
    });
  })();
</script>
@endpush
