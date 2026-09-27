@extends('layouts.public')

@section('title', 'Plainte envoyée | OEV')

@section('content')
<section class="public-section">
  <div class="container px-3 px-lg-4">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-7">
        <div class="suivi-card text-center">
          <div class="merci-icon"><i class="bi bi-check-lg"></i></div>
          <h1 class="h3 fw-bold text-dark mb-2">Merci, votre message a bien été transmis</h1>
          <p class="text-muted mb-4">
            Votre avis compte : nos agents vont en prendre connaissance pour faire avancer la prise en charge des OEV.
            @if ($contactable)
              Nous vous contacterons si nous avons besoin de précisions.
            @else
              Vous n'avez pas laissé de contact : pour compléter votre message, vous pouvez en envoyer un nouveau en citant cette référence.
            @endif
          </p>

          <div class="recepisse-box">
            <span class="recepisse-label">Référence de votre message</span>
            <span class="recepisse-value">{{ $reference }}</span>
          </div>

          <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
            <a href="{{ route('public.home') }}" class="btn btn-primary px-4 fw-bold">
              <i class="bi bi-house-door me-1"></i> Retour à l'accueil
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
