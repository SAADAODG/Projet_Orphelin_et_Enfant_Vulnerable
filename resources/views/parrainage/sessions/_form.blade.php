{{--
  Champs d'une session de parrainage (création et modification).
  Variables : $session, $sessionsOrigine
  Une session validée ne laisse modifier que la description et l'enveloppe ($figee).
--}}
@use('App\Models\SessionParrainage')
@use('App\Support\Montant')
@php
  $figee = $session->exists && ! $session->parametresModifiables();
  // Numéro attribué automatiquement : affiché seulement une fois la session enregistrée
  $colonne = $session->exists ? 'col-sm-6 col-lg-3' : 'col-sm-6 col-lg-4';
  $montant = fn ($valeur) => $valeur === null || $valeur === '' ? '' : (is_numeric($valeur) ? Montant::nombre($valeur) : $valeur);
@endphp

@if ($figee)
  <div class="alert alert-info small">
    <i class="bi bi-lock me-1" aria-hidden="true"></i>
    La session est « {{ $session->libelleEtat() }} » : le plafond et les options sont figés. La description et l’enveloppe peuvent encore changer.
  </div>
@endif

<div class="row g-3">
  <div class="col-12">
    <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
    <input class="form-control @error('description') is-invalid @enderror" id="description" name="description" type="text" maxlength="255"
           value="{{ old('description', $session->description) }}" placeholder="Ex : Session principale 2026-2027" required>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="{{ $colonne }}">
    <label class="form-label" for="annee">Année scolaire <span class="text-danger">*</span></label>
    <input class="form-control @error('annee') is-invalid @enderror" id="annee" name="annee" type="text" maxlength="9" pattern="\d{4}-\d{4}"
           value="{{ old('annee', $session->annee) }}" placeholder="2026-2027" required @disabled($figee)>
    @error('annee')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  @if ($session->exists)
    <div class="col-sm-6 col-lg-3">
      <label class="form-label" for="numero">Numéro de session</label>
      <input class="form-control" id="numero" type="text" value="n°{{ $session->numero }}" readonly>
    </div>
  @endif
  <div class="{{ $colonne }}">
    <label class="form-label" for="type_appui">Type d’appui <span class="text-danger">*</span></label>
    <select class="form-select @error('type_appui') is-invalid @enderror" id="type_appui" name="type_appui" required @disabled($figee)>
      @foreach (SessionParrainage::TYPES_APPUI as $cle => $libelle)
        <option value="{{ $cle }}" @selected(old('type_appui', $session->type_appui) === $cle)>{{ $libelle }}</option>
      @endforeach
    </select>
    @error('type_appui')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="{{ $colonne }}">
    <label class="form-label" for="date_ouverture">Date d’ouverture <span class="text-danger">*</span></label>
    <input class="form-control @error('date_ouverture') is-invalid @enderror" id="date_ouverture" name="date_ouverture" type="date"
           value="{{ old('date_ouverture', $session->date_ouverture?->toDateString()) }}" required @disabled($figee)>
    @error('date_ouverture')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-sm-6">
    <label class="form-label" for="enveloppe">Enveloppe budgétaire (FCFA)</label>
    <input class="form-control @error('enveloppe') is-invalid @enderror" id="enveloppe" name="enveloppe" type="text" inputmode="numeric"
           value="{{ $montant(old('enveloppe', $session->enveloppe)) }}" placeholder="Facultative, peut être renseignée plus tard" aria-describedby="aide-enveloppe">
    @error('enveloppe')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text" id="aide-enveloppe">Renseignée, elle n’est jamais dépassée par les OEV retenus. Sans enveloppe, le total des OEV choisis est affiché.</div>
  </div>
  <div class="col-sm-6">
    <label class="form-label" for="plafond_beneficiaire">Plafond par bénéficiaire (FCFA)</label>
    <input class="form-control @error('plafond_beneficiaire') is-invalid @enderror" id="plafond_beneficiaire" name="plafond_beneficiaire" type="text" inputmode="numeric"
           value="{{ $montant(old('plafond_beneficiaire', $session->plafond_beneficiaire)) }}" placeholder="Facultatif" @disabled($figee)>
    @error('plafond_beneficiaire')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

  <div class="col-12">
    <fieldset class="border rounded p-3">
      <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Options de sélection</legend>
      <div class="form-check mt-2">
        <input type="hidden" name="quotas_actifs" value="0">
        <input class="form-check-input" type="checkbox" id="quotas_actifs" name="quotas_actifs" value="1"
               @checked(old('quotas_actifs', $session->quotas_actifs)) @disabled($figee)>
        <label class="form-check-label" for="quotas_actifs">Appliquer des quotas par région <span class="text-muted small">(répartition de l’enveloppe : elle doit être renseignée)</span></label>
      </div>
      <div class="form-check mt-2">
        <input type="hidden" name="exclure_deja_appuyes" value="0">
        <input class="form-check-input" type="checkbox" id="exclure_deja_appuyes" name="exclure_deja_appuyes" value="1"
               @checked(old('exclure_deja_appuyes', $session->exclure_deja_appuyes)) @disabled($figee)>
        <label class="form-check-label" for="exclure_deja_appuyes">Exclure les OEV déjà appuyés par un partenaire pour la même nature d’appui</label>
      </div>
    </fieldset>
  </div>
</div>
