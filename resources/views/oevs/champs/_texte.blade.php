{{--
  Champ de saisie simple.
  Variables : $nom, $label, $valeur, $type (défaut 'text'), $max (longueur max), $requis (bool),
              $placeholder, $attributs (chaîne HTML additionnelle, ex. min/max d'une date), $erreur (message JS)
--}}
<label class="form-label" for="{{ $nom }}">{{ $label }} @if ($requis ?? false)<span class="text-danger">*</span>@endif</label>
<input class="form-control @error($nom) is-invalid @enderror" id="{{ $nom }}" name="{{ $nom }}" type="{{ $type ?? 'text' }}"
       value="{{ $valeur }}" @isset($max) maxlength="{{ $max }}" @endisset @if ($requis ?? false) required @endif
       @isset($placeholder) placeholder="{{ $placeholder }}" @endisset {!! $attributs ?? '' !!} data-recap="{{ $nom }}">
<div class="invalid-feedback">{{ $errors->first($nom) ?: ($erreur ?? 'Valeur invalide.') }}</div>
