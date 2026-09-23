@extends('layouts.public')

@section('title', 'À propos | OEV')

@section('content')
<div class="bg-light py-4 border-bottom">
  <div class="container px-3 px-lg-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('public.home') }}" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">À propos du programme OEV</li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">À propos du programme OEV</h1>
    <p class="text-muted mb-0">Découvrez nos missions et les critères d'accès aux services destinés aux orphelins et enfants vulnérables.</p>
  </div>
</div>

<section class="public-section">
  <div class="container px-3 px-lg-4">
    <div class="row g-5 align-items-center mb-5">
      <div class="col-12 col-lg-6">
        <span class="badge text-bg-primary px-3 py-2 text-uppercase mb-2">Cadre Institutionnel</span>
        <h2 class="h2 fw-bold text-dark mb-3">Le programme OEV au service des enfants</h2>
        <p class="text-secondary lead fs-6 mb-3">
          Le programme Orphelins et Enfants Vulnérables (OEV) assure le recensement, l'orientation sociale et le suivi des enfants ayant besoin d'une protection ou d'un accompagnement.
        </p>
        <p class="text-muted mb-4">
          L'accompagnement est défini après l'étude de la situation de l'enfant et peut couvrir l'éducation, la santé, la protection sociale et l'insertion selon les dispositifs disponibles.
        </p>

        <div class="row g-3">
          <div class="col-6">
            <div class="p-3 bg-light rounded-3 border-start border-4 border-primary">
              <h3 class="h6 fw-bold mb-1">Protection Légale</h3>
              <p class="small text-muted mb-0">Garantie des droits fondamentaux et priorité administrative.</p>
            </div>
          </div>
          <div class="col-6">
            <div class="p-3 bg-light rounded-3 border-start border-4 border-success">
              <h3 class="h6 fw-bold mb-1">Soutien Éducatif</h3>
              <p class="small text-muted mb-0">Prise en charge intégrale des études jusqu'à la majorité.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-primary text-white">
          <h3 class="h4 fw-bold mb-3"><i class="bi bi-award-fill me-2"></i> Critères d'Éligibilité</h3>
          <ul class="list-unstyled mb-4">
            <li class="mb-3 d-flex align-items-start gap-2">
              <i class="bi bi-check-circle-fill text-warning fs-5"></i>
              <span>Enfants privés de soutien parental ou exposés à une situation de vulnérabilité.</span>
            </li>
            <li class="mb-3 d-flex align-items-start gap-2">
              <i class="bi bi-check-circle-fill text-warning fs-5"></i>
              <span>Enfants nécessitant une protection, un accompagnement social ou un accès aux services essentiels.</span>
            </li>
            <li class="mb-3 d-flex align-items-start gap-2">
              <i class="bi bi-check-circle-fill text-warning fs-5"></i>
              <span>Évaluation de la situation et présentation des pièces justificatives disponibles.</span>
            </li>
          </ul>

          <div class="pt-3 border-top border-white-50">
            <a href="{{ route('public.demande') }}" class="btn btn-light fw-bold px-4 py-2 text-primary">
              <i class="bi bi-file-earmark-plus-fill me-1"></i> Soumettre une Demande
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- FAQ -->
    <div class="row justify-content-center mt-5">
      <div class="col-12 col-lg-9">
        <div class="text-center mb-4">
          <h2 class="h3 fw-bold text-dark">Foire Aux Questions (FAQ)</h2>
          <p class="text-muted">Réponses aux interrogations les plus fréquentes des tuteurs.</p>
        </div>

        <div class="accordion" id="faqAccordion">
          <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
            <h3 class="accordion-header">
              <button class="accordion-button fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true">
                Comment savoir si un enfant peut bénéficier du programme OEV ?
              </button>
            </h3>
            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
              <div class="accordion-body text-muted">
                Soumettez une demande en ligne ou contactez le service social compétent afin qu'une évaluation de la situation soit réalisée.
              </div>
            </div>
          </div>

          <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                Quels types d'accompagnement peuvent être proposés ?
              </button>
            </h3>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body text-muted">
                Selon les besoins évalués, l'accompagnement peut concerner la scolarité, la santé, le soutien psychosocial, la protection et l'orientation vers des services partenaires.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
