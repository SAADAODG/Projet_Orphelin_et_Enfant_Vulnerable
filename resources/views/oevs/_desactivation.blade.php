{{--
  Désactivation d'un OEV intégré (décès, majorité…) : il ne bénéficie plus d'aucune aide.
  Le DP la demande, le niveau central la valide ou la refuse, désactive directement et réactive.
  Variables : $oev, $utilisateur
--}}
@use('App\Models\Oev')
@php
  $central = $utilisateur->can('intégrer OEV');
  $peutDemander = $central || ($utilisateur->can('constituer dossiers') && $oev->estDansLePerimetreDe($utilisateur));
  $motifAffiche = $oev->libelleMotifDesactivation() . ($oev->desactivation_commentaire ? ' — ' . $oev->desactivation_commentaire : '');
@endphp

@error('desactivation')
  <div class="alert alert-danger mt-3" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ $message }}</div>
@enderror

@if ($oev->estDesactive())
  <section class="oev-action oev-action--rejet mt-3">
    <div>
      <div class="fw-bold">
        <i class="bi bi-person-slash me-1" aria-hidden="true"></i>
        OEV désactivé{{ $oev->desactive_at ? ' le ' . $oev->desactive_at->format('d/m/Y') : '' }}{{ $oev->auteurDesactivation ? ' par ' . $oev->auteurDesactivation->name : '' }}
      </div>
      <div class="small mt-1"><strong>Motif :</strong> {{ $motifAffiche }}</div>
      <div class="small text-muted mt-1">Il ne bénéficie plus d’aucune aide (appui d’un partenaire ou parrainage de l’État).</div>
    </div>
    @if ($central)
      <button class="btn btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#formReactivation" aria-expanded="{{ $errors->has('motif_reactivation') ? 'true' : 'false' }}" aria-controls="formReactivation">
        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Réactiver
      </button>
      <form class="collapse w-100 {{ $errors->has('motif_reactivation') ? 'show' : '' }}" id="formReactivation" method="POST" action="{{ route('oevs.reactiver', $oev) }}" data-confirm="Réactiver cet OEV ? Il pourra de nouveau bénéficier des aides.">
        @csrf
        <label class="form-label fw-semibold" for="motif_reactivation">Motif de la réactivation <span class="text-danger">*</span></label>
        <textarea class="form-control @error('motif_reactivation') is-invalid @enderror" id="motif_reactivation" name="motif_reactivation" rows="2" maxlength="2000" required placeholder="ex : désactivation enregistrée par erreur">{{ old('motif_reactivation') }}</textarea>
        @error('motif_reactivation')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <button class="btn btn-dark mt-2" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Confirmer la réactivation</button>
      </form>
    @endif
  </section>
@elseif ($oev->desactivationDemandee())
  <section class="oev-action oev-action--warning mt-3">
    <div>
      <div class="fw-bold">
        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
        Désactivation demandée{{ $oev->desactivation_demandee_at ? ' le ' . $oev->desactivation_demandee_at->format('d/m/Y') : '' }}{{ $oev->demandeurDesactivation ? ' par ' . $oev->demandeurDesactivation->name : '' }} — en attente du niveau central
      </div>
      <div class="small mt-1"><strong>Motif :</strong> {{ $motifAffiche }}</div>
      <div class="small text-muted mt-1">L’OEV reste bénéficiaire tant que la demande n’est pas validée.</div>
    </div>
    @if ($central)
      <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('oevs.desactivation.decider', $oev) }}" data-confirm="Valider la désactivation ? L’OEV ne bénéficiera plus d’aucune aide." data-confirm-danger>
          @csrf
          <input type="hidden" name="decision" value="valider">
          <button class="btn btn-danger" type="submit"><i class="bi bi-person-slash" aria-hidden="true"></i> Valider la désactivation</button>
        </form>
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#formRefusDesactivation" aria-expanded="{{ $errors->has('motif_refus') ? 'true' : 'false' }}" aria-controls="formRefusDesactivation">
          <i class="bi bi-x-lg" aria-hidden="true"></i> Refuser
        </button>
      </div>
      <form class="collapse w-100 {{ $errors->has('motif_refus') ? 'show' : '' }}" id="formRefusDesactivation" method="POST" action="{{ route('oevs.desactivation.decider', $oev) }}">
        @csrf
        <input type="hidden" name="decision" value="refuser">
        <label class="form-label fw-semibold" for="motif_refus">Motif du refus <span class="text-danger">*</span></label>
        <textarea class="form-control @error('motif_refus') is-invalid @enderror" id="motif_refus" name="motif_refus" rows="2" maxlength="2000" required>{{ old('motif_refus') }}</textarea>
        @error('motif_refus')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <button class="btn btn-secondary mt-2" type="submit"><i class="bi bi-x-lg" aria-hidden="true"></i> Refuser la demande</button>
      </form>
    @endif
  </section>
@elseif ($peutDemander)
  <div class="mt-3 text-end">
    <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formDesactivation" aria-expanded="{{ $errors->hasAny(['desactivation_motif', 'desactivation_commentaire']) ? 'true' : 'false' }}" aria-controls="formDesactivation">
      <i class="bi bi-person-slash" aria-hidden="true"></i> {{ $central ? 'Désactiver l’OEV' : 'Demander la désactivation' }}
    </button>
  </div>
  <form class="collapse oev-action oev-action--danger mt-2 {{ $errors->hasAny(['desactivation_motif', 'desactivation_commentaire']) ? 'show' : '' }}" id="formDesactivation" method="POST" action="{{ route('oevs.desactiver', $oev) }}"
        data-confirm="{{ $central ? 'Désactiver cet OEV ? Il ne bénéficiera plus d’aucune aide.' : 'Transmettre la demande de désactivation au niveau central ?' }}" data-confirm-danger>
    @csrf
    <div class="w-100">
      <p class="small mb-2">
        Un OEV désactivé ne bénéficie plus d’aucune aide (décès, majorité, sortie de la vulnérabilité…).
        @unless ($central) La désactivation prend effet après validation par le niveau central. @endunless
      </p>
      <div class="row g-2">
        <div class="col-sm-5">
          <label class="form-label fw-semibold" for="desactivation_motif">Motif <span class="text-danger">*</span></label>
          <select class="form-select @error('desactivation_motif') is-invalid @enderror" id="desactivation_motif" name="desactivation_motif" required>
            <option value="">Choisir…</option>
            @foreach (Oev::MOTIFS_DESACTIVATION as $cle => $libelle)
              <option value="{{ $cle }}" @selected(old('desactivation_motif') === $cle)>{{ $libelle }}</option>
            @endforeach
          </select>
          @error('desactivation_motif')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-7">
          <label class="form-label fw-semibold" for="desactivation_commentaire">Précisions <span class="text-muted small">(obligatoires pour « Autre »)</span></label>
          <textarea class="form-control @error('desactivation_commentaire') is-invalid @enderror" id="desactivation_commentaire" name="desactivation_commentaire" rows="2" maxlength="2000" placeholder="ex : date du décès, pièce justificative reçue…">{{ old('desactivation_commentaire') }}</textarea>
          @error('desactivation_commentaire')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <button class="btn btn-danger btn-sm mt-2" type="submit"><i class="bi bi-person-slash" aria-hidden="true"></i> {{ $central ? 'Désactiver' : 'Envoyer la demande' }}</button>
    </div>
  </form>
@endif
