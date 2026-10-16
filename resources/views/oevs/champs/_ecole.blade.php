{{--
  Choix d'une école parmi celles de la commune de l'enfant (étape 1), chargées à la demande
  (Paramètres > Écoles) ; « Autre » pour un établissement hors liste, alors saisi dans un champ à part.
  Variables : $nom, $label, $valeur (id d'école, « autre » ou ''), $requis (bool),
              $vide (libellé du choix vide quand la liste est chargée, ex. « Non renseigné »)
--}}
@php($requis ??= false)
<div data-bloc-ecole>
  <label class="form-label" for="{{ $nom }}">{{ $label }} @if ($requis)<span class="text-danger">*</span>@endif</label>
  <input class="form-control form-control-sm mb-1" type="search" placeholder="Rechercher une école dans la liste…" aria-label="Rechercher une école" data-filtre-ecoles hidden>
  <select class="form-select @error($nom) is-invalid @enderror" id="{{ $nom }}" name="{{ $nom }}" @if ($requis) required @endif
          data-ecoles data-url="{{ route('localites.ecoles-commune', '__COMMUNE__') }}" data-valeur="{{ $valeur }}" data-vide="{{ $vide }}">
    <option value="">Choisissez d’abord la commune de l’enfant (étape 1)</option>
    <option value="autre" @selected($valeur === 'autre')>Autre établissement (non répertorié)</option>
  </select>
  <div class="form-text" data-aide-ecoles></div>
  <div class="invalid-feedback">{{ $errors->first($nom) ?: 'L’établissement est obligatoire.' }}</div>
</div>
