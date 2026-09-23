@extends('layouts.public')

@section('title', 'Suivi de demande | OEV')

@section('content')
<div class="bg-light py-4 border-bottom">
  <div class="container px-3 px-lg-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('public.home') }}" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">Suivi de Récépissé & Dossier</li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">Suivi de l'État d'Avancement de la Demande</h1>
    <p class="text-muted mb-0">Entrez votre numéro de récépissé pour connaître la situation en temps réel de votre dossier.</p>
  </div>
</div>

<section class="public-section">
  <div class="container px-3 px-lg-4">
    <!-- Formulaire de recherche par N° de Récépissé -->
    <div class="row justify-content-center mb-5">
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
          <form action="{{ route('public.suivi') }}" method="GET" class="row g-2">
            <div class="col-12 col-md-8">
              <div class="input-group input-group-lg">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="recepisse" class="form-control bg-light border-start-0 fs-6" placeholder="ex: OEV-2026-8942" value="{{ request('recepisse') }}" required>
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

    @if (request()->filled('recepisse'))
    <!-- Carte de résultats : à remplacer par les données de la recherche backend -->
    <div class="row justify-content-center">
      <div class="col-12 col-lg-10">
        <div class="suivi-card">
          <!-- Header du dossier -->
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
            <div>
              <span class="badge text-bg-primary px-3 py-2 fs-6 mb-2">Récépissé N° REC-2026-8942</span>
              <h2 class="h4 fw-bold text-dark mb-0">Demande de Prise en Charge Scolaire & Médicale</h2>
              <p class="text-muted small mb-0">Dossier OEV : <strong>exemple de démonstration</strong></p>
            </div>
            <div class="text-end">
              <span class="badge text-bg-warning px-3 py-2 fs-6">
                <i class="bi bi-hourglass-split me-1"></i> En cours d'instruction
              </span>
              <div class="mt-2">
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                  <i class="bi bi-printer me-1"></i> Imprimer Récépissé
                </button>
              </div>
            </div>
          </div>

          <!-- Stepper Dynamique d'Avancement -->
          <div class="timeline-stepper">
            <div class="step-item completed">
              <div class="step-circle"><i class="bi bi-check-lg"></i></div>
              <div class="step-label">1. Soumission</div>
              <div class="step-sub">10 août 2026</div>
            </div>

            <div class="step-item completed">
              <div class="step-circle"><i class="bi bi-check-lg"></i></div>
              <div class="step-label">2. Évaluation sociale</div>
              <div class="step-sub">Pièces conformes</div>
            </div>

            <div class="step-item active">
              <div class="step-circle"><i class="bi bi-gear-wide-connected"></i></div>
              <div class="step-label">3. Validation Resp.</div>
              <div class="step-sub">En cours d'avis</div>
            </div>

            <div class="step-item">
              <div class="step-circle">4</div>
              <div class="step-label">4. Carte / Prestation</div>
              <div class="step-sub">À venir</div>
            </div>
          </div>

          <!-- Détails & Historique -->
          <div class="row g-4 mt-2">
            <div class="col-12 col-md-6">
              <div class="p-3 bg-light rounded-3">
                <h3 class="h6 fw-bold text-dark mb-3"><i class="bi bi-person-lines-fill text-primary me-2"></i> Informations du Dossier</h3>
                <ul class="list-unstyled small mb-0">
                  <li class="mb-2"><strong>Nom & Prénom :</strong> Sarah Ahmed</li>
                  <li class="mb-2"><strong>Référence du dossier :</strong> OEV-2026-8942</li>
                  <li class="mb-2"><strong>Tuteur Légal :</strong> M. Oumar Ahmed (+226 70 00 11 22)</li>
                  <li class="mb-2"><strong>Province :</strong> Kadiogo (Ouagadougou)</li>
                  <li><strong>Type de Demande :</strong> Prise en charge médicale et scolarité 2026</li>
                </ul>
              </div>
            </div>

            <div class="col-12 col-md-6">
              <div class="p-3 bg-light rounded-3">
                <h3 class="h6 fw-bold text-dark mb-3"><i class="bi bi-clock-history text-primary me-2"></i> Historique du Traitement</h3>
                <div class="timeline-list small">
                  <div class="mb-2">
                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> 10/08/2026 à 14h30 :</span>
                    <span>Demande enregistrée sur la plateforme web. Récépissé généré.</span>
                  </div>
                  <div class="mb-2">
                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> 11/08/2026 à 09h15 :</span>
                    <span>Vérification des pièces justificatives par l'équipe OEV (Pièces jugées conformes).</span>
                  </div>
                  <div>
                    <span class="text-primary fw-bold"><i class="bi bi-arrow-repeat me-1"></i> 12/08/2026 à 11h00 :</span>
                    <span>Transmis au responsable OEV pour validation de la prise en charge.</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
    @else
      <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
          <div class="alert alert-info border-0 shadow-sm" role="status">
            <i class="bi bi-info-circle me-2"></i>
            Saisissez un numéro de récépissé pour consulter l'état de votre demande.
          </div>
        </div>
      </div>
    @endif
  </div>
</section>
@endsection
