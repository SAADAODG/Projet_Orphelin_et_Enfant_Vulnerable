@extends('layouts.public')

@section('title', 'Suivi du signalement | OEV')

@section('content')
@php use App\Models\Signalement; @endphp
<div class="bg-light py-3 border-bottom">
  <div class="container px-3 px-lg-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('public.home') }}" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">Suivi du signalement</li>
      </ol>
    </nav>
    <h1 class="h4 fw-bold text-dark mb-1">Suivi du signalement</h1>
    <p class="text-muted small mb-0">Entrez le numéro de récépissé reçu lors de votre signalement pour connaître la réponse de nos agents.</p>
  </div>
</div>

<section class="public-section suivi-section">
  <div class="container px-3 px-lg-4">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-8">
        <!-- Recherche -->
        <form action="{{ route('public.suivi') }}" method="GET" class="suivi-recherche">
          <i class="bi bi-search" aria-hidden="true"></i>
          <input type="text" name="recepisse" class="text-uppercase" placeholder="N° de récépissé, ex : OEV-2026-K7Q2ZP" value="{{ $recepisse }}" aria-label="Numéro de récépissé" required>
          <button type="submit">Rechercher</button>
        </form>

        @if ($recepisse === '')
          <p class="suivi-info"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Le numéro figure sur le récépissé PDF téléchargé lors de votre signalement.</p>
        @elseif (! $signalement)
          <div class="suivi-alerte"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Aucun signalement ne correspond au récépissé <strong>{{ $recepisse }}</strong>. Vérifiez le numéro saisi.</div>
        @else
          @php
            $statut = $signalement->statut;
            $rejete = $statut === Signalement::REJETE;
            $valide = in_array($statut, [Signalement::VALIDE, Signalement::CLOTURE], true);
            $cloture = $statut === Signalement::CLOTURE;
            $prise = $cloture && $signalement->decision === Signalement::PRISE_EN_CHARGE;

            // État de chaque étape : fait | actif | refus | a-venir | inactif
            $etapes = [
              ['titre' => 'Signalement reçu', 'icone' => 'bi-inbox', 'etat' => 'fait', 'date' => $signalement->created_at->format('d/m/Y')],
              ['titre' => 'Examen', 'icone' => 'bi-search', 'etat' => $rejete ? 'refus' : ($valide ? 'fait' : 'actif'), 'date' => $signalement->traite_le?->format('d/m/Y') ?? 'En cours'],
              ['titre' => "Contact avec l'enfant", 'icone' => 'bi-house-door', 'etat' => $rejete ? 'inactif' : ($cloture ? 'fait' : ($valide ? 'actif' : 'a-venir')), 'date' => $cloture ? $signalement->date_visite?->format('d/m/Y') : ($valide ? 'À venir' : '')],
              ['titre' => 'Décision', 'icone' => $prise ? 'bi-house-heart' : ($cloture ? 'bi-slash-circle' : 'bi-flag'), 'etat' => $rejete ? 'inactif' : ($cloture ? ($prise ? 'fait' : 'refus') : 'a-venir'), 'date' => $cloture ? $signalement->decision_libelle : ''],
            ];
          @endphp

          <div class="suivi-carte">
            <div class="suivi-entete">
              <div>
                <span class="suivi-numero">N° {{ $signalement->recepisse }}</span>
                <h2>Signalement de {{ $signalement->enfant_nom_complet }}</h2>
                <p>Envoyé le {{ $signalement->created_at->format('d/m/Y à H\hi') }}</p>
              </div>
              @if ($cloture)
                <span class="suivi-badge {{ $prise ? 'suivi-badge--ok' : 'suivi-badge--non' }}">{{ $signalement->decision_libelle }}</span>
              @elseif ($valide)
                <span class="suivi-badge suivi-badge--info">Validé</span>
              @elseif ($rejete)
                <span class="suivi-badge suivi-badge--non">Non éligible</span>
              @else
                <span class="suivi-badge suivi-badge--attente">En cours d'examen</span>
              @endif
            </div>

            <!-- Étapes -->
            <ol class="suivi-etapes">
              @foreach ($etapes as $etape)
                <li class="suivi-etape suivi-etape--{{ $etape['etat'] }}">
                  <span class="suivi-etape-rond">
                    @if ($etape['etat'] === 'fait')<i class="bi bi-check-lg" aria-hidden="true"></i>
                    @elseif ($etape['etat'] === 'refus')<i class="bi bi-x-lg" aria-hidden="true"></i>
                    @else<i class="bi {{ $etape['icone'] }}" aria-hidden="true"></i>@endif
                  </span>
                  <span class="suivi-etape-titre">{{ $etape['titre'] }}</span>
                  @if ($etape['date'])<span class="suivi-etape-date">{{ $etape['date'] }}</span>@endif
                </li>
              @endforeach
            </ol>

            <!-- Message selon l'avancement -->
            @if ($cloture && $prise)
              <div class="suivi-message suivi-message-success">
                <i class="bi bi-house-heart-fill" aria-hidden="true"></i>
                <div>
                  <h3>L'enfant est pris en charge</h3>
                  <p class="mb-0">Suite au contact avec l'enfant le {{ $signalement->date_visite?->format('d/m/Y') }}, il a été admis au programme de prise en charge des OEV. Merci pour votre signalement.</p>
                  @if ($signalement->message_declarant)<p class="mt-2 mb-0"><strong>Message de l'agent :</strong> {{ $signalement->message_declarant }}</p>@endif
                </div>
              </div>
            @elseif ($cloture)
              <div class="suivi-message suivi-message-danger">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <div>
                  <h3>Pas de prise en charge</h3>
                  <p class="mb-0">Suite au contact avec l'enfant le {{ $signalement->date_visite?->format('d/m/Y') }}, sa situation n'a pas été retenue pour une prise en charge par le programme OEV.</p>
                  @if ($signalement->message_declarant)<p class="mt-2 mb-0"><strong>Message de l'agent :</strong> {{ $signalement->message_declarant }}</p>@endif
                  <p class="mt-2 mb-0 small">Pour toute question, contactez le guichet OEV au +226 25 30 00 00.</p>
                </div>
              </div>
            @elseif ($valide)
              <div class="suivi-message suivi-message-success">
                <i class="bi bi-telephone-outbound-fill" aria-hidden="true"></i>
                <div>
                  <h3>Signalement validé</h3>
                  <p class="mb-0">Un agent vous contactera prochainement au <strong>{{ $signalement->declarant_telephone }}</strong> pour convenir d'un passage au domicile de l'enfant.</p>
                </div>
              </div>
            @elseif ($rejete)
              <div class="suivi-message suivi-message-danger">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <div>
                  <h3>Signalement non éligible</h3>
                  <p class="mb-0">Après examen, la situation signalée ne remplit pas les critères d'éligibilité au programme de prise en charge des OEV.</p>
                  @if ($signalement->motif_rejet)<p class="mt-2 mb-0"><strong>Motif :</strong> {{ $signalement->motif_rejet }}</p>@endif
                  <p class="mt-2 mb-0 small">Pour toute question, contactez le guichet OEV au +226 25 30 00 00.</p>
                </div>
              </div>
            @else
              <div class="suivi-message suivi-message-info">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <div>
                  <h3>En cours d'examen</h3>
                  <p class="mb-0">Votre signalement a bien été enregistré. Un agent est en train de l'examiner. Revenez consulter cette page avec votre récépissé.</p>
                </div>
              </div>
            @endif
          </div>
        @endif
      </div>
    </div>
  </div>
</section>
@endsection
