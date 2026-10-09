{{--
  Filtre à choix unique. Variables : $nom, $label, $options [valeur => libellé], $valeur (valeur actuelle),
  $tous (libellé de l'option vide, défaut « Tous »).
--}}
<label class="form-label small fw-semibold mb-1" for="f-{{ $nom }}">{{ $label }}</label>
<select class="form-select form-select-sm {{ $valeur !== null ? 'is-filtre' : '' }}" id="f-{{ $nom }}" name="{{ $nom }}">
  <option value="">{{ $tous ?? 'Tous' }}</option>
  @foreach ($options as $cle => $libelle)
    <option value="{{ $cle }}" @selected((string) $valeur === (string) $cle)>{{ $libelle }}</option>
  @endforeach
</select>
