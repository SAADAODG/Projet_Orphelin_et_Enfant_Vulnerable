{{--
  Champs d'une session de parrainage (création et modification).
  Variables : $session, $parrains, $contributions (parrain_id => montant), $sessionsOrigine
  Une session validée ne laisse modifier que la description ($figee).
--}}
@use('App\Models\SessionParrainage')
@use('App\Support\Montant')
@php
  $figee = $session->exists && ! $session->parametresModifiables();
  $source = old('source_financement', $session->source_financement);
  $montant = fn ($valeur) => $valeur === null || $valeur === '' ? '' : (is_numeric($valeur) ? Montant::nombre($valeur) : $valeur);
@endphp

@if ($figee)
  <div class="alert alert-info small">
    <i class="bi bi-lock me-1" aria-hidden="true"></i>
    La session est « {{ $session->libelleEtat() }} » : l’enveloppe, le plafond, le financement et les options sont figés. Seule la description peut encore changer.
  </div>
@endif

<div class="row g-3">
  <div class="col-12">
    <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
    <input class="form-control @error('description') is-invalid @enderror" id="description" name="description" type="text" maxlength="255"
           value="{{ old('description', $session->description) }}" placeholder="Ex : Session principale 2026-2027" required>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-sm-6 col-lg-3">
    <label class="form-label" for="annee">Année scolaire <span class="text-danger">*</span></label>
    <input class="form-control @error('annee') is-invalid @enderror" id="annee" name="annee" type="text" maxlength="9" pattern="\d{4}-\d{4}"
           value="{{ old('annee', $session->annee) }}" placeholder="2026-2027" required @disabled($figee)>
    @error('annee')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6 col-lg-3">
    <label class="form-label" for="numero">Numéro de session <span class="text-danger">*</span></label>
    <input class="form-control @error('numero') is-invalid @enderror" id="numero" name="numero" type="number" min="1" max="99"
           value="{{ old('numero', $session->numero) }}" required @disabled($figee)>
    @error('numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6 col-lg-3">
    <label class="form-label" for="type_appui">Type d’appui <span class="text-danger">*</span></label>
    <select class="form-select @error('type_appui') is-invalid @enderror" id="type_appui" name="type_appui" required @disabled($figee)>
      @foreach (SessionParrainage::TYPES_APPUI as $cle => $libelle)
        <option value="{{ $cle }}" @selected(old('type_appui', $session->type_appui) === $cle)>{{ $libelle }}</option>
      @endforeach
    </select>
    @error('type_appui')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6 col-lg-3">
    <label class="form-label" for="date_ouverture">Date d’ouverture <span class="text-danger">*</span></label>
    <input class="form-control @error('date_ouverture') is-invalid @enderror" id="date_ouverture" name="date_ouverture" type="date"
           value="{{ old('date_ouverture', $session->date_ouverture?->toDateString()) }}" required @disabled($figee)>
    @error('date_ouverture')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-sm-6">
    <label class="form-label" for="enveloppe">Enveloppe budgétaire (FCFA) <span class="text-danger">*</span></label>
    <input class="form-control @error('enveloppe') is-invalid @enderror" id="enveloppe" name="enveloppe" type="text" inputmode="numeric"
           value="{{ $montant(old('enveloppe', $session->enveloppe)) }}" placeholder="Ex : 250 000 000" required @disabled($figee)>
    @error('enveloppe')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Une seule enveloppe, quel que soit le type d’appui.</div>
  </div>
  <div class="col-sm-6">
    <label class="form-label" for="plafond_beneficiaire">Plafond par bénéficiaire (FCFA) <span class="text-danger">*</span></label>
    <input class="form-control @error('plafond_beneficiaire') is-invalid @enderror" id="plafond_beneficiaire" name="plafond_beneficiaire" type="text" inputmode="numeric"
           value="{{ $montant(old('plafond_beneficiaire', $session->plafond_beneficiaire)) }}" placeholder="Ex : 75 000" required @disabled($figee)>
    @error('plafond_beneficiaire')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Montant retenu pour un OEV : le plus petit de ses frais réels et de ce plafond.</div>
  </div>

  <div class="col-sm-6">
    <label class="form-label" for="source_financement">Source de financement <span class="text-danger">*</span></label>
    <select class="form-select @error('source_financement') is-invalid @enderror" id="source_financement" name="source_financement" required @disabled($figee) data-source-financement>
      @foreach (SessionParrainage::SOURCES_FINANCEMENT as $cle => $libelle)
        <option value="{{ $cle }}" @selected($source === $cle)>{{ $libelle }}</option>
      @endforeach
    </select>
    @error('source_financement')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6">
    <label class="form-label" for="session_origine_id">Session d’origine <span class="text-muted small">(session de rattrapage)</span></label>
    <select class="form-select @error('session_origine_id') is-invalid @enderror" id="session_origine_id" name="session_origine_id" @disabled($figee)>
      <option value="">Aucune</option>
      @foreach ($sessionsOrigine as $origine)
        <option value="{{ $origine->id }}" @selected((string) old('session_origine_id', $session->session_origine_id) === (string) $origine->id)>{{ $origine->reference() }} — {{ $origine->description }}</option>
      @endforeach
    </select>
    @error('session_origine_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  {{-- Parrains contributeurs : financement partenaire ou mixte --}}
  <div class="col-12" data-contributions @if (! in_array($source, SessionParrainage::SOURCES_AVEC_PARRAINS, true)) hidden @endif>
    <fieldset class="border rounded p-3">
      <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Contributions des parrains (FCFA)</legend>
      @error('contributions')<div class="alert alert-danger py-2 small mt-2 mb-0">{{ $message }}</div>@enderror
      @forelse ($parrains as $parrain)
        <div class="row g-2 align-items-center mt-1">
          <label class="col-sm-7 col-form-label" for="contribution-{{ $parrain->id }}">
            {{ $parrain->nom }} <span class="text-muted small">· {{ $parrain->libelleType() }}@unless ($parrain->actif) · inactif @endunless</span>
          </label>
          <div class="col-sm-5">
            <input class="form-control @error('contributions.' . $parrain->id) is-invalid @enderror" id="contribution-{{ $parrain->id }}" name="contributions[{{ $parrain->id }}]"
                   type="text" inputmode="numeric" value="{{ $montant(old('contributions.' . $parrain->id, $contributions[$parrain->id] ?? '')) }}" placeholder="0" @disabled($figee)>
            @error('contributions.' . $parrain->id)<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
      @empty
        <p class="text-muted small mt-2 mb-0">Aucun parrain actif n’est enregistré. Les parrains seront gérés dans l’onglet « Parrains ».</p>
      @endforelse
    </fieldset>
  </div>

  <div class="col-12">
    <fieldset class="border rounded p-3">
      <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Options de sélection</legend>
      <div class="form-check mt-2">
        <input type="hidden" name="bloquer_depassement_enveloppe" value="0">
        <input class="form-check-input" type="checkbox" id="bloquer_depassement_enveloppe" name="bloquer_depassement_enveloppe" value="1"
               @checked(old('bloquer_depassement_enveloppe', $session->bloquer_depassement_enveloppe)) @disabled($figee)>
        <label class="form-check-label" for="bloquer_depassement_enveloppe">Bloquer le dépassement de l’enveloppe <span class="text-muted small">(RG-04)</span></label>
      </div>
      <div class="form-check mt-2">
        <input type="hidden" name="quotas_actifs" value="0">
        <input class="form-check-input" type="checkbox" id="quotas_actifs" name="quotas_actifs" value="1"
               @checked(old('quotas_actifs', $session->quotas_actifs)) @disabled($figee)>
        <label class="form-check-label" for="quotas_actifs">Appliquer des quotas par région <span class="text-muted small">(RG-04 — montants saisis dans la vue « Quotas »)</span></label>
      </div>
      <div class="form-check mt-2">
        <input type="hidden" name="exclure_deja_appuyes" value="0">
        <input class="form-check-input" type="checkbox" id="exclure_deja_appuyes" name="exclure_deja_appuyes" value="1"
               @checked(old('exclure_deja_appuyes', $session->exclure_deja_appuyes)) @disabled($figee)>
        <label class="form-check-label" for="exclure_deja_appuyes">Exclure les OEV déjà appuyés par un partenaire pour la même nature d’appui <span class="text-muted small">(RG-07)</span></label>
      </div>
    </fieldset>
  </div>
</div>

@push('scripts')
<script>
  // Les contributions ne s'affichent que pour un financement partenaire ou mixte
  document.addEventListener('DOMContentLoaded', function () {
    var source = document.querySelector('[data-source-financement]');
    var bloc = document.querySelector('[data-contributions]');
    if (!source || !bloc) return;
    source.addEventListener('change', function () {
      bloc.hidden = !@json(SessionParrainage::SOURCES_AVEC_PARRAINS).includes(source.value);
    });
  });
</script>
@endpush
