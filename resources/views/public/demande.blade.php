@extends('layouts.public')

@section('title', 'Nouvelle demande | OEV')

@section('content')
<div class="bg-light py-4 border-bottom">
  <div class="container px-3 px-lg-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('public.home') }}" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">Demande de Prise en Charge</li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">Formulaire de Demande de Prise en Charge</h1>
    <p class="text-muted mb-0">Remplissez les informations ci-dessous pour soumettre une demande de prise en charge OEV.</p>
  </div>
</div>

<section class="public-section">
  <div class="container px-3 px-lg-4">
    <div class="row g-4 justify-content-center">
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
          <form action="{{ route('public.suivi') }}" method="GET" enctype="multipart/form-data" class="needs-validation">
            <!-- Étape 1 : Recherche / Référence Décret -->
            <div class="mb-4 pb-3 border-bottom">
              <h2 class="h5 fw-bold text-primary mb-3">
                <span class="badge bg-primary me-2">1</span> Identification de l'enfant
              </h2>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="decretNum">Numéro du Décret de Référence *</label>
                  <input type="text" class="form-control" id="decretNum" name="reference" placeholder="Référence du dossier (si connue)">
                  <div class="invalid-feedback">Le numéro de décret est requis.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="oevCode">Identifiant OEV (si connu)</label>
                  <input type="text" class="form-control" id="oevCode" name="oev_code" placeholder="ex: OEV-2025-0482">
                </div>
              </div>
            </div>

            <!-- Étape 2 : Informations de l'enfant -->
            <div class="mb-4 pb-3 border-bottom">
              <h2 class="h5 fw-bold text-primary mb-3">
                <span class="badge bg-primary me-2">2</span> Informations de l'enfant
              </h2>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="nomEnfant">Nom de famille *</label>
                  <input type="text" class="form-control" id="nomEnfant" name="nom_enfant" placeholder="Entrez le nom" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="prenomEnfant">Prénom(s) *</label>
                  <input type="text" class="form-control" id="prenomEnfant" name="prenom_enfant" placeholder="Entrez le prénom" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="dateNaissance">Date de naissance *</label>
                  <input type="date" class="form-control" id="dateNaissance" name="date_naissance" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="lieuNaissance">Lieu de naissance / Localité *</label>
                  <input type="text" class="form-control" id="lieuNaissance" name="lieu_naissance" placeholder="ex: Ouagadougou, Kadiogo" required>
                </div>
              </div>
            </div>

            <!-- Étape 3 : Tuteur Légal & Prestation -->
            <div class="mb-4 pb-3 border-bottom">
              <h2 class="h5 fw-bold text-primary mb-3">
                <span class="badge bg-primary me-2">3</span> Tuteur Légal & Type de Prestation
              </h2>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="nomTuteur">Nom & Prénom du Tuteur Légal *</label>
                  <input type="text" class="form-control" id="nomTuteur" name="nom_tuteur" placeholder="Nom complet du tuteur" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="telTuteur">Numéro de Téléphone du Tuteur *</label>
                  <input type="tel" class="form-control" id="telTuteur" name="telephone_tuteur" placeholder="+226 XX XX XX XX" required>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold" for="typePrestation">Nature de la Prestation Demandée *</label>
                  <select class="form-select" id="typePrestation" name="type_prestation" required>
                    <option value="">Sélectionnez le type de prise en charge</option>
                    <option value="education">Prise en charge Scolaire / Universitaire (Scolarité, fournitures)</option>
                    <option value="sante">Prise en charge Médicale (Soins, ordonnances, hôpital)</option>
                    <option value="attestation">Demande d'attestation OEV</option>
                    <option value="allocation">Allocation Sociale / Soutien Exceptionnel</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold" for="detailsDemande">Détails complémentaires de la demande</label>
                  <textarea class="form-control" id="detailsDemande" name="details" rows="3" placeholder="Précisez le besoin spécifique de l'enfant."></textarea>
                </div>
              </div>
            </div>

            <!-- Étape 4 : Pièces Justificatives -->
            <div class="mb-4">
              <h2 class="h5 fw-bold text-primary mb-3">
                <span class="badge bg-primary me-2">4</span> Pièces Justificatives (Fichiers PDF / Images)
              </h2>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="pieceActe">Acte de naissance *</label>
                  <input type="file" class="form-control" id="pieceActe" name="acte_naissance" accept=".pdf,.png,.jpg,.jpeg" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="pieceCNIB">Pièce d'Identité du Tuteur *</label>
                  <input type="file" class="form-control" id="pieceCNIB" name="piece_identite_tuteur" accept=".pdf,.png,.jpg,.jpeg" required>
                </div>
              </div>
            </div>

            <!-- Boutons de soumission -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 border-top">
              <a href="{{ route('public.home') }}" class="btn btn-outline-secondary px-4">Annuler</a>
              <button type="submit" class="btn btn-primary px-5 py-2 fw-bold fs-6 shadow-sm">
                <i class="bi bi-send-fill me-2"></i> Soumettre la Demande
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
          <h3 class="h5 fw-bold text-dark mb-3"><i class="bi bi-shield-check text-primary me-2"></i> Assistance & Info</h3>
          <p class="small text-muted mb-3">
            Toute demande soumise génère immédiatement un <strong>Numéro de Récépissé</strong> confidentiel à conserver.
          </p>
          <div class="alert alert-info border-0 rounded-3 mb-0 small">
            <i class="bi bi-info-circle-fill me-1"></i>
            Les dossiers complets sont examinés par les agents chargés de l'accompagnement sous <strong>48 heures ouvrées</strong>.
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-primary text-white">
          <h4 class="h6 fw-bold text-uppercase text-white-50 mb-2">Besoin d'aide ?</h4>
          <p class="small mb-3">Contactez le guichet d'assistance OEV en cas de difficulté lors du remplissage.</p>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-telephone-inbound fs-3"></i>
            <div>
              <div class="fw-bold fs-5">+226 25 30 00 00</div>
              <div class="small text-white-50">Appel gratuit - 8h à 16h</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
