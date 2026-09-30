{{--
  Choix multiples (cases à cocher), envoyés sous forme de tableau « $nom[] ».
  Variables : $nom, $label, $options [valeur => libellé], $valeurs (tableau des valeurs cochées),
              $requis (bool : au moins une case, défaut false),
              $automatiques [valeur => condition] : case cochée d'office selon d'autres réponses, non modifiable,
              $conditions [valeur => condition] : option proposée seulement si la condition est remplie.
  Les conditions suivent la syntaxe data-si du formulaire (ex. « sexe:F », « mere_vivante:non||pere_vivant:non »).
--}}
@php
  $valeurs = array_map('strval', (array) ($valeurs ?? []));
  $automatiques = $automatiques ?? [];
  $conditions = $conditions ?? [];
@endphp
<span class="form-label d-block" id="{{ $nom }}-label">{{ $label }} @if ($requis ?? false)<span class="text-danger">*</span>@endif</span>
<div class="oev-cases" role="group" aria-labelledby="{{ $nom }}-label" @if ($requis ?? false) data-groupe-requis="{{ $nom }}[]" @endif>
  @foreach ($options as $cle => $libelle)
    <span class="oev-case" @isset($conditions[$cle]) data-si="{{ $conditions[$cle] }}" @endisset>
      @if (isset($automatiques[$cle]))
        {{-- Case déduite des réponses : désactivée (non envoyée), le serveur la recalcule --}}
        <input class="btn-check" type="checkbox" id="{{ $nom }}-{{ $cle }}" value="{{ $cle }}" disabled data-auto="{{ $automatiques[$cle] }}" data-nom-auto="{{ $nom }}[]" data-libelle="{{ $libelle }}">
        <label class="btn btn-sm btn-outline-secondary" for="{{ $nom }}-{{ $cle }}" title="Coché automatiquement d’après les réponses"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ $libelle }} <i class="bi bi-magic ms-1 oev-auto" aria-hidden="true"></i></label>
      @else
        <input class="btn-check" type="checkbox" name="{{ $nom }}[]" id="{{ $nom }}-{{ $cle }}" value="{{ $cle }}" @checked(in_array((string) $cle, $valeurs, true)) data-libelle="{{ $libelle }}">
        <label class="btn btn-sm btn-outline-secondary" for="{{ $nom }}-{{ $cle }}"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ $libelle }}</label>
      @endif
    </span>
  @endforeach
</div>
@if ($automatiques)
  <div class="form-text"><i class="bi bi-magic" aria-hidden="true"></i> Les situations marquées de cette icône sont cochées automatiquement d’après les réponses précédentes.</div>
@endif
<div class="text-danger small mt-1" data-erreur-groupe="{{ $nom }}[]" hidden>Cochez au moins une case.</div>
@error($nom)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
