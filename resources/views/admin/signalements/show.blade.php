@extends('layouts.app')

@section('title', 'Signalement '.$signalement->recepisse.' | Espace Agent OEV')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-file-earmark-person" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Signalement N° {{ $signalement->recepisse }}</p>
        <h1 class="h3 mb-1">{{ $signalement->enfant_nom_complet }}</h1>
        <p class="text-muted mb-0">Reçu le {{ $signalement->created_at->format('d/m/Y à H:i') }}</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.signalements.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la liste</a>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success border-0 shadow-sm" role="status">
      <i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}
    </div>
  @endif

  <div class="row g-3">
    <div class="col-12 col-xl-8">
      <section class="panel mb-3">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title"><i class="bi bi-person-hearts" aria-hidden="true"></i><span>L'enfant</span></h2>
        </div>
        <dl class="row detail-list mb-0">
          <div class="col-sm-6"><dt>Nom et prénom(s)</dt><dd>{{ $signalement->enfant_nom_complet }}</dd></div>
          <div class="col-sm-6"><dt>Âge estimé</dt><dd>{{ $signalement->enfant_age }} ans</dd></div>
          <div class="col-sm-6"><dt>Vulnérabilité</dt><dd>{{ $signalement->vulnerabilite_libelle }}</dd></div>
          <div class="col-sm-6"><dt>Région / Province</dt><dd>{{ $signalement->region }} — {{ $signalement->province }}</dd></div>
          <div class="col-12"><dt>Ville, village ou quartier</dt><dd class="mb-0">{{ $signalement->localite }}</dd></div>
        </dl>
      </section>

      <section class="panel">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title"><i class="bi bi-person-lines-fill" aria-hidden="true"></i><span>Le déclarant</span></h2>
        </div>
        <dl class="row detail-list mb-0">
          <div class="col-sm-6"><dt>Nom et prénom(s)</dt><dd>{{ $signalement->declarant_nom_complet }}</dd></div>
          <div class="col-sm-6"><dt>Lien avec l'enfant</dt><dd>{{ $signalement->lien_libelle }}</dd></div>
          <div class="col-sm-6"><dt>Téléphone</dt><dd><a href="tel:{{ $signalement->declarant_telephone }}">{{ $signalement->declarant_telephone }}</a></dd></div>
          <div class="col-sm-6"><dt>Profession</dt><dd>{{ $signalement->declarant_profession }}</dd></div>
          <div class="col-12"><dt>Adresse</dt><dd class="mb-0">{{ $signalement->declarant_adresse }}</dd></div>
        </dl>
      </section>
    </div>

    <div class="col-12 col-xl-4">
      <section class="panel">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Décision</span></h2>
        </div>

        @if ($signalement->statut === 'en_attente')
          <p class="text-muted small">Ce signalement est en attente. Votre décision sera visible par le déclarant dans « Suivi du signalement ».</p>
          <form method="POST" action="{{ route('admin.signalements.valider', $signalement) }}" onsubmit="return confirm('Valider ce signalement ? Le déclarant sera informé qu\'il sera contacté pour une visite à domicile.');">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-success w-100 mb-2">
              <i class="bi bi-check2-circle" aria-hidden="true"></i> Valider le signalement
            </button>
          </form>
          <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejetModal">
            <i class="bi bi-x-circle" aria-hidden="true"></i> Rejeter
          </button>
        @elseif ($signalement->statut === 'valide')
          <div class="alert alert-success mb-0">
            <strong><i class="bi bi-check-circle me-1" aria-hidden="true"></i> Validé</strong> le {{ $signalement->traite_le->format('d/m/Y à H:i') }}.
            <div class="small mt-1">Prochaine étape : contacter le déclarant au {{ $signalement->declarant_telephone }} pour organiser la visite à domicile.</div>
          </div>
        @else
          <div class="alert alert-danger mb-0">
            <strong><i class="bi bi-x-circle me-1" aria-hidden="true"></i> Rejeté</strong> le {{ $signalement->traite_le->format('d/m/Y à H:i') }}.
            @if ($signalement->motif_rejet)
              <div class="small mt-1"><strong>Motif :</strong> {{ $signalement->motif_rejet }}</div>
            @endif
          </div>
        @endif
      </section>
    </div>
  </div>
</div>

@if ($signalement->statut === 'en_attente')
  <div class="modal fade" id="rejetModal" tabindex="-1" aria-labelledby="rejetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST" action="{{ route('admin.signalements.rejeter', $signalement) }}">
        @csrf
        @method('PATCH')
        <div class="modal-header">
          <h2 class="modal-title h5" id="rejetModalLabel">Rejeter le signalement</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">Le déclarant verra un message de non-éligibilité en consultant son récépissé.</p>
          <label class="form-label fw-semibold" for="motif_rejet">Motif (facultatif, visible par le déclarant)</label>
          <textarea class="form-control" id="motif_rejet" name="motif_rejet" rows="4" maxlength="1000" placeholder="ex : L'enfant bénéficie déjà d'une prise en charge."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-danger">Confirmer le rejet</button>
        </div>
      </form>
    </div>
  </div>
@endif
@endsection
