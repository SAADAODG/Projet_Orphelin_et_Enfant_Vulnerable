{{--
  Choix unique : boutons radio (peu d'options) ou liste déroulante.
  Variables : $nom, $label, $options [valeur => libellé], $valeur (valeur actuelle, chaîne),
              $requis (bool, défaut false), $mode ('boutons' | 'liste', défaut 'boutons'), $aide (texte, optionnel),
              $masquerSi ([valeur => condition], mode liste : option retirée quand la condition est remplie)
--}}
@php
  $requis = $requis ?? false;
  $mode = $mode ?? 'boutons';
  $valeur = $valeur === null ? null : (string) $valeur;
  $masquerSi = $masquerSi ?? [];
@endphp
@if ($mode === 'liste')
  <label class="form-label" for="{{ $nom }}">{{ $label }} @if ($requis)<span class="text-danger">*</span>@endif</label>
  <select class="form-select @error($nom) is-invalid @enderror" id="{{ $nom }}" name="{{ $nom }}" @if ($requis) required @endif data-recap="{{ $nom }}">
    <option value="">Sélectionner…</option>
    @foreach ($options as $cle => $libelle)
      <option value="{{ $cle }}" @selected($valeur === (string) $cle) @isset($masquerSi[$cle]) data-masquer-si="{{ $masquerSi[$cle] }}" @endisset>{{ $libelle }}</option>
    @endforeach
  </select>
@else
  <span class="form-label d-block" id="{{ $nom }}-label">{{ $label }} @if ($requis)<span class="text-danger">*</span>@endif</span>
  <div class="oev-choix" role="radiogroup" aria-labelledby="{{ $nom }}-label">
    @foreach ($options as $cle => $libelle)
      <input class="btn-check" type="radio" name="{{ $nom }}" id="{{ $nom }}-{{ $cle }}" value="{{ $cle }}" @checked($valeur === (string) $cle) @if ($requis) required @endif data-libelle="{{ $libelle }}">
      <label class="btn btn-outline-primary" for="{{ $nom }}-{{ $cle }}">{{ $libelle }}</label>
    @endforeach
  </div>
@endif
@isset($aide)<div class="form-text">{{ $aide }}</div>@endisset
<div class="text-danger small mt-1" data-erreur-groupe="{{ $nom }}" hidden>Ce choix est obligatoire.</div>
@error($nom)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
