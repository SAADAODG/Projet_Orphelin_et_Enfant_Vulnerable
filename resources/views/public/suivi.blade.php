@extends('layouts.public')

@section('title', 'Suivi du signalement | OEV')

@section('content')
<div class="bg-light py-4 border-bottom">
  <div class="container px-3 px-lg-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('public.home') }}" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">Suivi du signalement</li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">Suivi du Signalement</h1>
    <p class="text-muted mb-0">Entrez le numéro de récépissé reçu lors de votre signalement pour connaître la réponse de nos agents.</p>
  </div>
</div>

<section class="public-section">
  <div class="container px-3 px-lg-4">
    <div class="row justify-content-center mb-5">
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
          <form action="{{ route('public.suivi') }}" method="GET" class="row g-2">
            <div class="col-12 col-md-8">
              <div class="input-group input-group-lg">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="recepisse" class="form-control bg-light border-start-0 fs-6 text-uppercase" placeholder="ex : OEV-2026-K7Q2ZP" value="{{ $recepisse }}" required>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold fs-6 shadow-sm">
                <i class="bi bi-arrow-right-circle me-1"></i> Rechercher
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    @if ($recepisse === '')
      <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
          <div class="alert alert-info border-0 shadow-sm" role="status">
            <i class="bi bi-info-circle me-2"></i>
            Saisissez votre numéro de récépissé pour consulter l'état de votre signalement.
          </div>
        </div>
      </div>
    @elseif (! $signalement)
      <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
          <div class="alert alert-warning border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Aucun signalement ne correspond au récépissé <strong>{{ $recepisse }}</strong>. Vérifiez le numéro saisi.
          </div>
        </div>
      </div>
    @else
      @php
        $traite = $signalement->statut !== \App\Models\Signalement::EN_ATTENTE;
        $rejete = $signalement->statut === \App\Models\Signalement::REJETE;
      @endphp
      <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
          <div class="suivi-card">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
              <div>
                <span class="badge text-bg-primary px-3 py-2 fs-6 mb-2">Récépissé N° {{ $signalement->recepisse }}</span>
                <h2 class="h4 fw-bold text-dark mb-0">Signalement de {{ $signalement->enfant_nom_complet }}</h2>
                <p class="text-muted small mb-0">Envoyé le {{ $signalement->created_at->format('d/m/Y à H\hi') }}</p>
              </div>
              @if ($signalement->statut === \App\Models\Signalement::VALIDE)
                <span class="badge text-bg-success px-3 py-2 fs-6"><i class="bi bi-check-circle me-1"></i> Validé</span>
              @elseif ($rejete)
                <span class="badge text-bg-danger px-3 py-2 fs-6"><i class="bi bi-x-circle me-1"></i> Non éligible</span>
              @else
                <span class="badge text-bg-warning px-3 py-2 fs-6"><i class="bi bi-hourglass-split me-1"></i> En cours d'examen</span>
              @endif
            </div>

            <div class="timeline-stepper">
              <div class="step-item completed">
                <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="step-label">1. Signalement reçu</div>
                <div class="step-sub">{{ $signalement->created_at->format('d/m/Y') }}</div>
              </div>
              <div class="step-item {{ $traite ? 'completed' : 'active' }}">
                <div class="step-circle"><i class="bi {{ $traite ? 'bi-check-lg' : 'bi-hourglass-split' }}"></i></div>
                <div class="step-label">2. Examen par un agent</div>
                <div class="step-sub">{{ $traite ? 'Terminé' : 'En cours' }}</div>
              </div>
              <div class="step-item {{ $rejete ? 'rejected' : ($traite ? 'completed' : '') }}">
                <div class="step-circle">
                  @if ($rejete)<i class="bi bi-x-lg"></i>@elseif ($traite)<i class="bi bi-house-heart"></i>@else 3 @endif
                </div>
                <div class="step-label">3. Décision</div>
                <div class="step-sub">{{ $traite ? $signalement->traite_le->format('d/m/Y') : 'À venir' }}</div>
              </div>
            </div>

            @if ($signalement->statut === \App\Models\Signalement::VALIDE)
              <div class="suivi-message suivi-message-success">
                <i class="bi bi-house-heart-fill"></i>
                <div>
                  <h3>Nous avons bien reçu votre signalement</h3>
                  <p class="mb-0">Votre signalement a été validé par nos services. Un agent vous contactera prochainement au <strong>{{ $signalement->declarant_telephone }}</strong> pour convenir d'un passage au domicile de l'enfant.</p>
                </div>
              </div>
            @elseif ($rejete)
              <div class="suivi-message suivi-message-danger">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                  <h3>Signalement non éligible</h3>
                  <p class="mb-0">Après examen, nous sommes au regret de vous informer que la situation signalée ne remplit pas les critères d'éligibilité au programme de prise en charge des OEV.</p>
                  @if ($signalement->motif_rejet)
                    <p class="mt-2 mb-0"><strong>Motif :</strong> {{ $signalement->motif_rejet }}</p>
                  @endif
                  <p class="mt-2 mb-0 small">Pour toute question, contactez le guichet OEV au +226 25 30 00 00.</p>
                </div>
              </div>
            @else
              <div class="suivi-message suivi-message-info">
                <i class="bi bi-hourglass-split"></i>
                <div>
                  <h3>Signalement en cours d'examen</h3>
                  <p class="mb-0">Votre signalement a bien été enregistré. Un agent est en train de l'examiner. Revenez consulter cette page avec votre récépissé.</p>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>
    @endif
  </div>
</section>
@endsection
