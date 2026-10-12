{{--
  Filtre à choix multiple : liste déroulante de cases à cocher (« l'une ou l'autre » des valeurs).
  Variables : $nom (envoyé en $nom[]), $label, $options (valeur => libellé), $valeurs (cochées), $tous (libellé sans choix)
  La recherche est relancée à la fermeture de la liste (voir le script de la page).
--}}
@php
  $valeurs = array_map('strval', (array) $valeurs);
  $choisis = array_intersect_key($options, array_flip($valeurs));
  $resume = match (count($choisis)) {
      0 => $tous ?? 'Tous',
      1 => reset($choisis),
      default => count($choisis) . ' choisis',
  };
@endphp
<label class="form-label" for="m-{{ $nom }}">{{ $label }}</label>
<div class="dropdown" data-multi>
  <button class="form-select form-select-sm text-start text-truncate" id="m-{{ $nom }}" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
    {{ $resume }}
  </button>
  <div class="dropdown-menu sel-multi-menu p-2">
    @foreach ($options as $cle => $libelle)
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="m-{{ $nom }}-{{ $loop->index }}" name="{{ $nom }}[]" value="{{ $cle }}" @checked(in_array((string) $cle, $valeurs, true))>
        <label class="form-check-label" for="m-{{ $nom }}-{{ $loop->index }}">{{ $libelle }}</label>
      </div>
    @endforeach
  </div>
</div>
