{{--
  Champs d'un appui. Variables : $appui, $parrains, $natures, $oevChoisi
  Si l'OEV a déjà un appui de même nature cette année (session « doublons »), l'agent confirme avec un motif (RG-01).
--}}
@use('App\Models\NatureAppui')
@use('App\Support\Montant')
@php
  $doublons = session('doublons', []);
  $montant = old('montant', $appui->montant);
  $codeAutre = $natures->firstWhere('code', NatureAppui::AUTRE)?->id;
@endphp

@if ($doublons)
  <div class="alert alert-warning" role="alert">
    <p class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> Cet OEV a déjà un appui de même nature pour cette année (RG-01) :</p>
    <ul class="mb-2">
      @foreach ($doublons as $doublon)
        <li>{{ $doublon }}</li>
      @endforeach
    </ul>
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="confirmer_doublon" name="confirmer_doublon" value="1" @checked(old('confirmer_doublon')) required>
      <label class="form-check-label" for="confirmer_doublon">Je confirme l’enregistrement de ce deuxième appui</label>
    </div>
    <label class="form-label fw-semibold mt-2" for="motif_doublon">Motif <span class="text-danger">*</span></label>
    <textarea class="form-control @error('motif_doublon') is-invalid @enderror" id="motif_doublon" name="motif_doublon" rows="2" maxlength="2000" required>{{ old('motif_doublon') }}</textarea>
    @error('motif_doublon')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
@endif

<div class="row g-3">
  <div class="col-12" data-recherche-oev data-url="{{ route('parrainage.appuis.recherche-oev') }}">
    <label class="form-label" for="recherche_oev">OEV appuyé <span class="text-danger">*</span></label>
    <input type="hidden" name="oev_id" value="{{ old('oev_id', $appui->oev_id) }}" data-oev-id>
    <input class="form-control @error('oev_id') is-invalid @enderror" id="recherche_oev" type="search" autocomplete="off"
           placeholder="Tapez le code, le nom ou le prénom de l’enfant" value="{{ $oevChoisi ? $oevChoisi->reference() . ' — ' . $oevChoisi->nomComplet() : '' }}"
           aria-describedby="aide_oev" data-oev-saisie @disabled($appui->exists)>
    @error('oev_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text" id="aide_oev">
      @if ($appui->exists)
        L’OEV d’un appui enregistré ne change pas : en cas d’erreur, enregistrez un nouvel appui.
      @else
        Seuls les OEV intégrés de votre zone sont proposés.
      @endif
    </div>
    <div class="list-group mt-1" data-oev-resultats role="listbox" aria-label="OEV trouvés"></div>
  </div>

  <div class="col-sm-6">
    <label class="form-label" for="parrain_id">Parrain <span class="text-danger">*</span></label>
    <select class="form-select @error('parrain_id') is-invalid @enderror" id="parrain_id" name="parrain_id" required>
      <option value="">Choisir…</option>
      @foreach ($parrains as $parrain)
        <option value="{{ $parrain->id }}" @selected((string) old('parrain_id', $appui->parrain_id) === (string) $parrain->id)>{{ $parrain->nom }}@unless ($parrain->actif) (inactif)@endunless</option>
      @endforeach
    </select>
    @error('parrain_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6">
    <label class="form-label" for="annee">Année scolaire <span class="text-danger">*</span></label>
    <input class="form-control @error('annee') is-invalid @enderror" id="annee" name="annee" type="text" maxlength="9" pattern="\d{4}-\d{4}" value="{{ old('annee', $appui->annee) }}" placeholder="2026-2027" required>
    @error('annee')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-sm-6">
    <label class="form-label" for="nature_appui_id">Nature de l’appui <span class="text-danger">*</span></label>
    <select class="form-select @error('nature_appui_id') is-invalid @enderror" id="nature_appui_id" name="nature_appui_id" required data-nature data-code-autre="{{ $codeAutre }}">
      <option value="">Choisir…</option>
      @foreach ($natures as $nature)
        <option value="{{ $nature->id }}" @selected((string) old('nature_appui_id', $appui->nature_appui_id) === (string) $nature->id)>{{ $nature->libelle }}</option>
      @endforeach
    </select>
    @error('nature_appui_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6" data-precision @if ((string) old('nature_appui_id', $appui->nature_appui_id) !== (string) $codeAutre) hidden @endif>
    <label class="form-label" for="nature_precision">Précisez <span class="text-danger">*</span></label>
    <input class="form-control @error('nature_precision') is-invalid @enderror" id="nature_precision" name="nature_precision" type="text" maxlength="150" value="{{ old('nature_precision', $appui->nature_precision) }}">
    @error('nature_precision')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-sm-4">
    <label class="form-label" for="montant">Montant (FCFA)</label>
    <input class="form-control @error('montant') is-invalid @enderror" id="montant" name="montant" type="text" inputmode="numeric"
           value="{{ $montant === null || $montant === '' ? '' : (is_numeric($montant) ? Montant::nombre($montant) : $montant) }}" placeholder="Vide pour un appui en nature">
    @error('montant')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-4">
    <label class="form-label" for="date_debut">Date de début</label>
    <input class="form-control @error('date_debut') is-invalid @enderror" id="date_debut" name="date_debut" type="date" value="{{ old('date_debut', $appui->date_debut?->toDateString()) }}">
    @error('date_debut')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-4">
    <label class="form-label" for="date_fin">Date de fin</label>
    <input class="form-control @error('date_fin') is-invalid @enderror" id="date_fin" name="date_fin" type="date" value="{{ old('date_fin', $appui->date_fin?->toDateString()) }}">
    @error('date_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-12">
    <label class="form-label" for="observations">Observations</label>
    <textarea class="form-control @error('observations') is-invalid @enderror" id="observations" name="observations" rows="3" maxlength="2000">{{ old('observations', $appui->observations) }}</textarea>
    @error('observations')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Précision demandée seulement pour la nature « Autre »
    var nature = document.querySelector('[data-nature]');
    var precision = document.querySelector('[data-precision]');
    if (nature && precision) {
      nature.addEventListener('change', function () { precision.hidden = nature.value !== nature.dataset.codeAutre; });
    }

    // Recherche d'un OEV : propositions sous le champ, le choix remplit oev_id
    var bloc = document.querySelector('[data-recherche-oev]');
    if (!bloc) return;
    var saisie = bloc.querySelector('[data-oev-saisie]');
    var champId = bloc.querySelector('[data-oev-id]');
    var resultats = bloc.querySelector('[data-oev-resultats]');
    var minuteur;
    saisie.addEventListener('input', function () {
      champId.value = '';
      clearTimeout(minuteur);
      var terme = saisie.value.trim();
      if (terme.length < 2) { resultats.replaceChildren(); return; }
      minuteur = setTimeout(function () {
        fetch(bloc.dataset.url + '?q=' + encodeURIComponent(terme), { headers: { 'Accept': 'application/json' } })
          .then(function (reponse) { return reponse.ok ? reponse.json() : []; })
          .then(function (oevs) {
            resultats.replaceChildren();
            if (!oevs.length) {
              var vide = document.createElement('div');
              vide.className = 'list-group-item text-muted small';
              vide.textContent = 'Aucun OEV intégré trouvé dans votre zone.';
              resultats.append(vide);
            }
            oevs.forEach(function (oev) {
              var bouton = document.createElement('button');
              bouton.type = 'button';
              bouton.className = 'list-group-item list-group-item-action';
              bouton.setAttribute('role', 'option');
              bouton.textContent = oev.libelle;
              bouton.addEventListener('click', function () {
                champId.value = oev.id;
                saisie.value = oev.libelle;
                resultats.replaceChildren();
              });
              resultats.append(bouton);
            });
          });
      }, 250);
    });
  });
</script>
@endpush
