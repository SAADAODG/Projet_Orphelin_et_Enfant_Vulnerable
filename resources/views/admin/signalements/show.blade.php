@extends('layouts.app')

@section('title', 'Signalement '.$signalement->recepisse.' | Espace Agent OEV')

@section('content')
@php use App\Models\Signalement; @endphp
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="sig-detail">
    <!-- En-tête -->
    <div class="sig-detail-head">
      <a class="sig-retour" href="{{ route('admin.signalements.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Signalements</a>
      <div class="sig-detail-titre">
        <span class="sig-avatar sig-avatar--lg">{{ $signalement->initiales }}</span>
        <div>
          <h1>{{ $signalement->enfant_nom_complet }}</h1>
          <p>N° {{ $signalement->recepisse }} · reçu le {{ $signalement->created_at->format('d/m/Y à H:i') }}</p>
        </div>
        @if ($signalement->statut === Signalement::CLOTURE)
          <span class="sig-badge ms-auto {{ $signalement->decision === Signalement::PRISE_EN_CHARGE ? 'text-success bg-success-subtle' : 'text-danger bg-danger-subtle' }}">Clôturé · {{ $signalement->decision_libelle }}</span>
        @else
          <span class="sig-badge ms-auto text-{{ $signalement->statut_couleur }} bg-{{ $signalement->statut_couleur }}-subtle">{{ $signalement->statut_libelle }}</span>
        @endif
      </div>
    </div>

    @if (session('success'))
      <div class="alert alert-success border-0 py-2 small" role="status">
        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ session('success') }}
      </div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger border-0 py-2 small" role="alert">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>La clôture n'a pas pu être enregistrée : vérifiez le formulaire en bas de page.
      </div>
    @endif

    <!-- L'enfant -->
    <section class="sig-bloc">
      <h2><i class="bi bi-person-hearts" aria-hidden="true"></i> L'enfant</h2>
      <dl class="sig-grille">
        <div><dt>Nom et prénom(s)</dt><dd>{{ $signalement->enfant_nom_complet }}</dd></div>
        <div><dt>Âge estimé</dt><dd>{{ $signalement->enfant_age }} ans</dd></div>
        <div><dt>Région / Province</dt><dd>{{ $signalement->region?->nom ?? '—' }} — {{ $signalement->province?->nom ?? '—' }}</dd></div>
        <div><dt>Commune</dt><dd>{{ $signalement->commune?->nom ?? '—' }}</dd></div>
        <div><dt>Village, quartier ou secteur</dt><dd>{{ $signalement->localite }}</dd></div>
        <div class="sig-grille-large">
          <dt>Situation</dt>
          <dd>
            @foreach (array_filter(explode(',', (string) $signalement->vulnerabilite)) as $cle)
              <span class="sig-tag">{{ Signalement::VULNERABILITES[$cle] ?? $cle }}</span>
            @endforeach
            @if ($signalement->vulnerabilite_precision)<span class="sig-meta mt-1">Précision : {{ $signalement->vulnerabilite_precision }}</span>@endif
          </dd>
        </div>
      </dl>
    </section>

    <!-- Le déclarant -->
    <section class="sig-bloc">
      <h2><i class="bi bi-person-lines-fill" aria-hidden="true"></i> Le déclarant</h2>
      <dl class="sig-grille">
        <div><dt>Nom et prénom(s)</dt><dd>{{ $signalement->declarant_nom_complet }}</dd></div>
        <div><dt>Lien avec l'enfant</dt><dd>{{ $signalement->lien_libelle }}</dd></div>
        <div><dt>Téléphone</dt><dd><a href="tel:{{ $signalement->declarant_telephone }}">{{ $signalement->declarant_telephone }}</a></dd></div>
        <div><dt>Profession</dt><dd>{{ $signalement->declarant_profession }}</dd></div>
        <div class="sig-grille-large"><dt>Adresse</dt><dd>{{ $signalement->declarant_adresse }}</dd></div>
      </dl>
    </section>

    <!-- Historique -->
    <section class="sig-bloc">
      <h2><i class="bi bi-clock-history" aria-hidden="true"></i> Historique</h2>
      <ol class="sig-historique">
        <li class="is-fait">
          <strong>Signalement reçu</strong>
          <span>{{ $signalement->created_at->format('d/m/Y à H:i') }} · récépissé {{ $signalement->recepisse }}</span>
        </li>
        @if ($signalement->statut === Signalement::REJETE)
          <li class="is-refus">
            <strong>Rejeté</strong>
            <span>{{ $signalement->traite_le?->format('d/m/Y à H:i') }}@if ($signalement->agentTraitement) · par {{ $signalement->agentTraitement->name }}@endif</span>
            @if ($signalement->motif_rejet)<em>Motif : {{ $signalement->motif_rejet }}</em>@endif
          </li>
        @elseif ($signalement->traite_le)
          <li class="is-fait">
            <strong>Validé — contact à organiser</strong>
            <span>{{ $signalement->traite_le->format('d/m/Y à H:i') }}@if ($signalement->agentTraitement) · par {{ $signalement->agentTraitement->name }}@endif</span>
          </li>
        @else
          <li class="is-attente"><strong>En attente d'examen</strong><span>Aucune décision pour le moment</span></li>
        @endif
        @if ($signalement->statut === Signalement::CLOTURE)
          <li class="is-fait">
            <strong>Contact avec l'enfant</strong>
            <span>le {{ $signalement->date_visite?->format('d/m/Y') }}</span>
          </li>
          <li class="{{ $signalement->decision === Signalement::PRISE_EN_CHARGE ? 'is-fait' : 'is-refus' }}">
            <strong>Clôturé — {{ $signalement->decision_libelle }}</strong>
            <span>{{ $signalement->cloture_le?->format('d/m/Y à H:i') }}@if ($signalement->agentCloture) · par {{ $signalement->agentCloture->name }}@endif</span>
            @if ($signalement->compte_rendu)<em>Compte rendu : {{ $signalement->compte_rendu }}</em>@endif
            @if ($signalement->message_declarant)<em>Message au déclarant : {{ $signalement->message_declarant }}</em>@endif
          </li>
        @elseif ($signalement->statut === Signalement::VALIDE)
          <li class="is-attente"><strong>Contact avec l'enfant</strong><span>À faire — puis clôturer ci-dessous</span></li>
        @endif
      </ol>
    </section>

    <!-- Décision (en bas, à la suite des autres blocs) -->
    <section class="sig-bloc sig-bloc--decision">
      <h2><i class="bi bi-clipboard-check" aria-hidden="true"></i> Décision</h2>

      @if ($signalement->statut === Signalement::EN_ATTENTE)
        <p class="sig-aide">Examinez le signalement. S'il est validé, le déclarant sera informé qu'un agent le contactera pour une visite à domicile.</p>
        <div class="sig-actions">
          <form method="POST" action="{{ route('admin.signalements.valider', $signalement) }}" onsubmit="return confirm('Valider ce signalement ?');">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-success btn-sm px-3"><i class="bi bi-check2-circle" aria-hidden="true"></i> Valider</button>
          </form>
          <button type="button" class="btn btn-outline-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#rejetModal"><i class="bi bi-x-circle" aria-hidden="true"></i> Rejeter</button>
        </div>

      @elseif ($signalement->statut === Signalement::VALIDE)
        <p class="sig-aide">Une fois le contact établi avec l'enfant (visite à domicile), clôturez le signalement avec la décision. Elle sera visible par le déclarant.</p>
        <form method="POST" action="{{ route('admin.signalements.cloturer', $signalement) }}" class="sig-cloture" novalidate>
          @csrf
          @method('PATCH')
          <div class="sig-decisions">
            @foreach (Signalement::DECISIONS as $valeur => $libelle)
              <label class="sig-decision sig-decision--{{ $valeur === Signalement::PRISE_EN_CHARGE ? 'oui' : 'non' }}">
                <input type="radio" name="decision" value="{{ $valeur }}" {{ old('decision') === $valeur ? 'checked' : '' }} required>
                <span><i class="bi {{ $valeur === Signalement::PRISE_EN_CHARGE ? 'bi-house-heart' : 'bi-slash-circle' }}" aria-hidden="true"></i> {{ $libelle }}</span>
              </label>
            @endforeach
          </div>
          @error('decision')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

          <div class="row g-2">
            <div class="col-sm-4">
              <label class="form-label" for="date_visite">Date du contact</label>
              <input type="date" class="form-control form-control-sm @error('date_visite') is-invalid @enderror" id="date_visite" name="date_visite" value="{{ old('date_visite', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
              @error('date_visite')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
              <label class="form-label" for="compte_rendu">Compte rendu de la visite <span class="text-muted">(interne)</span></label>
              <textarea class="form-control form-control-sm" id="compte_rendu" name="compte_rendu" rows="3" maxlength="3000" placeholder="Observations, besoins identifiés, conditions de vie…">{{ old('compte_rendu') }}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="message_declarant">Message au déclarant <span class="text-muted">(facultatif, visible dans le suivi)</span></label>
              <textarea class="form-control form-control-sm" id="message_declarant" name="message_declarant" rows="2" maxlength="1000">{{ old('message_declarant') }}</textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm px-3 mt-3"><i class="bi bi-lock" aria-hidden="true"></i> Clôturer le signalement</button>
        </form>

      @elseif ($signalement->statut === Signalement::CLOTURE)
        <div class="sig-resultat {{ $signalement->decision === Signalement::PRISE_EN_CHARGE ? 'sig-resultat--oui' : 'sig-resultat--non' }}">
          <i class="bi {{ $signalement->decision === Signalement::PRISE_EN_CHARGE ? 'bi-house-heart' : 'bi-slash-circle' }}" aria-hidden="true"></i>
          <div>
            <strong>{{ $signalement->decision_libelle }}</strong>
            <span>Contact le {{ $signalement->date_visite?->format('d/m/Y') }} · clôturé le {{ $signalement->cloture_le?->format('d/m/Y') }}</span>
          </div>
        </div>

      @else
        <div class="sig-resultat sig-resultat--non">
          <i class="bi bi-x-circle" aria-hidden="true"></i>
          <div>
            <strong>Rejeté</strong>
            <span>le {{ $signalement->traite_le?->format('d/m/Y') }}@if ($signalement->motif_rejet) · {{ $signalement->motif_rejet }}@endif</span>
          </div>
        </div>
      @endif
    </section>
  </div>
</div>

@if ($signalement->statut === Signalement::EN_ATTENTE)
  <div class="modal fade" id="rejetModal" tabindex="-1" aria-labelledby="rejetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST" action="{{ route('admin.signalements.rejeter', $signalement) }}">
        @csrf
        @method('PATCH')
        <div class="modal-header">
          <h2 class="modal-title h6" id="rejetModalLabel">Rejeter le signalement</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">Le déclarant verra un message de non-éligibilité en consultant son récépissé.</p>
          <label class="form-label small fw-semibold" for="motif_rejet">Motif (facultatif, visible par le déclarant)</label>
          <textarea class="form-control form-control-sm" id="motif_rejet" name="motif_rejet" rows="3" maxlength="1000" placeholder="ex : L'enfant bénéficie déjà d'une prise en charge."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-danger btn-sm">Confirmer le rejet</button>
        </div>
      </form>
    </div>
  </div>
@endif
@endsection
