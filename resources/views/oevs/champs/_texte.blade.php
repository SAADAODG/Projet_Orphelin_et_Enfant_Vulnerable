{{--
  Champ de saisie simple.
  Variables : $nom, $label, $valeur, $type (défaut 'text'), $max (longueur max), $requis (bool),
              $requisSi (condition « champ:valeur » rendant le champ obligatoire, même syntaxe que data-si),
              $placeholder, $attributs (chaîne HTML additionnelle, ex. min/max d'une date), $erreur (message JS)
--}}
<label class="form-label" for="{{ $nom }}">{{ $label }} @if ($requis ?? false)<span class="text-danger">*</span>@elseif (isset($requisSi))<span class="text-danger" data-si="{{ $requisSi }}">*</span>@endif</label>
<input class="form-control @error($nom) is-invalid @enderror" id="{{ $nom }}" name="{{ $nom }}" type="{{ $type ?? 'text' }}"
       value="{{ $valeur }}" @isset($max) maxlength="{{ $max }}" @endisset @if ($requis ?? false) required @endif
       @isset($requisSi) data-requis-si="{{ $requisSi }}" @endisset
       @isset($placeholder) placeholder="{{ $placeholder }}" @endisset {!! $attributs ?? '' !!} data-recap="{{ $nom }}">
<div class="invalid-feedback">{{ $errors->first($nom) ?: ($erreur ?? 'Valeur invalide.') }}</div>
